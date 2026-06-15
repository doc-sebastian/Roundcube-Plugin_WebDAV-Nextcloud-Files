/**
 * WebDAV / Nextcloud files plugin for Roundcube - client script
 *
 * @license GNU GPLv3+
 */

(function () {
    'use strict';

    if (!window.rcmail) {
        return;
    }

    var dialog = null,        // current browser/dialog jQuery object
        cur_path = '/',       // current folder in the browser
        mode = 'attach',      // 'attach' | 'folder'
        folder_target = null, // input id to write the chosen folder into
        pending = false;

    rcmail.addEventListener('init', function () {
        if (rcmail.env.action === 'compose') {
            rcmail.register_command('plugin.webdav_files.open_browser', open_compose_browser, true);
            inject_compose_attach_button();
        }

        // message view (new window or reading pane) + main list view
        if (rcmail.env.task === 'mail') {
            rcmail.register_command('plugin.webdav_files.save_pdf', save_pdf);
            rcmail.register_command('plugin.webdav_files.save_attachments', open_save_attachments);

            if (rcmail.env.action === 'show' || rcmail.env.action === 'preview') {
                rcmail.enable_command('plugin.webdav_files.save_pdf', true);
                rcmail.enable_command('plugin.webdav_files.save_attachments', true);
                inject_message_attachment_button();
            } else {
                // main mailbox list: enable when a message is selected
                bind_list_selection();
            }

            // add icons to context-/more-menu entries regardless of how the
            // menu is built (Elastic core menu or the contextmenu plugin)
            bind_menu_decoration();
        }

        if (rcmail.env.task === 'settings') {
            rcmail.register_command('plugin.webdav_files.pick_folder', open_folder_picker, true);
            mark_settings_section();
        }

        rcmail.addEventListener('plugin.webdav_files.browser_data', on_browser_data);
        rcmail.addEventListener('plugin.webdav_files.attached', on_attached);
        rcmail.addEventListener('plugin.webdav_files.links', on_links);
        rcmail.addEventListener('plugin.webdav_files.attachment_list', on_attachment_list);
        rcmail.addEventListener('plugin.webdav_files.saved', on_saved);
        rcmail.addEventListener('plugin.webdav_files.pdf_saved', on_pdf_saved);
        rcmail.addEventListener('plugin.webdav_files.error', on_error);
    });

    function t(name) {
        return rcmail.gettext(name, 'webdav_files');
    }

    function conf() {
        return rcmail.env.webdav_files || {};
    }

    function configured() {
        return conf().configured || rcmail.env.webdav_files_configured;
    }

    function not_configured() {
        rcmail.display_message(t('error_notconfigured'), 'warning');
    }

    // Add an icon class to our settings section row in the sections list.
    // Done in JS to avoid fighting Elastic's CSS specificity.
    function mark_settings_section() {
        var apply = function () {
            // the sections list row id is rcmrow{section}
            var row = $('#rcmrowwebdav_files, tr.webdav_files, li.webdav_files');
            if (!row.length) {
                // fallback: find by the command argument in the row's link
                row = $('a').filter(function () {
                    var oc = ($(this).attr('onclick') || '') + ($(this).attr('href') || '');
                    return oc.indexOf('webdav_files') > -1 && oc.indexOf('preferences') > -1;
                }).closest('tr, li');
            }
            row.addClass('wdf-section-icon');
        };
        apply();
        // the list may render slightly after init
        setTimeout(apply, 300);
        setTimeout(apply, 1000);
    }

    // ------------------------------------------------------------------
    // Enable list-view commands based on the message selection
    // ------------------------------------------------------------------

    function bind_list_selection() {
        if (!rcmail.message_list) {
            return;
        }
        rcmail.message_list.addEventListener('select', function (list) {
            var has = list.get_selection(false).length > 0;
            rcmail.enable_command('plugin.webdav_files.save_pdf', has);
            rcmail.enable_command('plugin.webdav_files.save_attachments', has);
        });
    }

    // Inject a small cloud icon into any menu entry that triggers one of our
    // commands. Works for Elastic's core menus and the contextmenu plugin.
    function bind_menu_decoration() {
        var run = function () { setTimeout(decorate_menus, 60); };

        // right-click anywhere in the list opens the context menu
        $(document).on('contextmenu', run);
        // the "More" toolbar menu and others fire menu-open
        rcmail.addEventListener('menu-open', run);
        rcmail.addEventListener('contextmenu', run);
    }

    function decorate_menus() {
        var cmds = ['plugin.webdav_files.save_pdf', 'plugin.webdav_files.save_attachments'];

        cmds.forEach(function (cmd) {
            $('a[onclick*="' + cmd + '"], a[rel="' + cmd + '"]').each(function () {
                var a = $(this);
                // skip the real top toolbar buttons (they have their own :before icon)
                if (a.closest('.toolbar, #messagetoolbar').length) {
                    return;
                }
                if (!a.children('.wdf-menu-icon').length) {
                    a.prepend('<span class="wdf-menu-icon"></span>');
                }
            });
        });
    }

    function current_message_ref() {
        if (rcmail.env.uid) {
            return { _uid: rcmail.env.uid, _mbox: rcmail.env.mailbox };
        }
        if (rcmail.message_list) {
            var sel = rcmail.message_list.get_selection(false);
            if (sel.length) {
                return { _uid: sel[0], _mbox: rcmail.env.mailbox };
            }
        }
        return null;
    }

    // ------------------------------------------------------------------
    // Inject extra buttons into the attachment areas
    // ------------------------------------------------------------------

    // Compose: a button "from cloud storage" in the attachment options area,
    // next to the regular "Attach file" control.
    function inject_compose_attach_button() {
        var tries = 0,
            timer = setInterval(function () {
                tries++;
                var area = $('#compose-attachments').find('.formcontent, .boxcontent, form').first();
                if (!area.length) {
                    area = $('#compose-attachments');
                }
                if (area.length && !area.find('.wdf-attach-inline').length) {
                    var btn = $('<a href="#" class="button webdav-files attach-cloud wdf-attach-inline">')
                        .text(t('attachfromcloud_short'))
                        .attr('title', t('attachfromcloud_title'))
                        .on('click', function (e) {
                            e.preventDefault();
                            rcmail.command('plugin.webdav_files.open_browser');
                        });
                    area.append(btn);
                    clearInterval(timer);
                } else if (tries > 20) {
                    clearInterval(timer);
                }
            }, 300);
    }

    // Message view: a "save attachments to cloud" button below the attachment list.
    function inject_message_attachment_button() {
        if (!configured()) {
            return;
        }
        var tries = 0,
            timer = setInterval(function () {
                tries++;
                var list = $('#attachment-list');
                if (list.length && !$('.wdf-save-attachments-inline').length && list.children().length) {
                    var btn = $('<a href="#" class="button webdav-files save-attachments wdf-save-attachments-inline">')
                        .text(t('saveattachments'))
                        .attr('title', t('saveattachments_title'))
                        .on('click', function (e) {
                            e.preventDefault();
                            open_save_attachments();
                        });
                    // place below the list so it reads like a list action
                    list.after(btn);
                    clearInterval(timer);
                } else if (tries > 20) {
                    clearInterval(timer);
                }
            }, 300);
    }

    // ------------------------------------------------------------------
    // File browser (compose: attach / link)
    // ------------------------------------------------------------------

    function open_compose_browser() {
        if (!configured()) {
            return not_configured();
        }

        mode = 'attach';
        open_browser(t('browser_title'), [
            { text: t('attach'), 'class': 'mainaction attach', click: function () { submit_selection('attach'); } },
            { text: t('insertlink'), 'class': 'insertlink', click: function () { submit_selection('link'); } },
            { text: t('cancel'), 'class': 'cancel', click: function () { dialog.dialog('close'); } }
        ], true);
    }

    function open_folder_picker(target) {
        if (!configured()) {
            return not_configured();
        }

        mode = 'folder';
        folder_target = target;

        open_browser(t('pickfolder_title'), [
            { text: t('selectfolder'), 'class': 'mainaction select', click: choose_folder },
            { text: t('cancel'), 'class': 'cancel', click: function () { dialog.dialog('close'); } }
        ], false);
    }

    function open_browser(title, buttons, multiselect) {
        console.log('open_browser called with title:', title, 'buttons:', buttons, 'multiselect:', multiselect);
        
        var body = $('<div class="wdf-browser">');

        body.append(
            $('<div class="wdf-toolbar">')
                .append($('<span class="wdf-breadcrumb">'))
                .append(
                    $('<button type="button" class="button wdf-newfolder">')
                        .text(t('newfolder'))
                        .on('click', new_folder)
                )
        );

        if (mode === 'folder') {
            body.append($('<p class="wdf-folder-hint">').text(t('pickfolder_hint')));
        }

        body.append($('<ul class="wdf-list">').toggleClass('multiselect', !!multiselect));

        console.log('About to show popup dialog with rcmail.show_popup_dialog');
        
        // Store current path for potential later use
        var initialPath = cur_path;
        
        try {
            dialog = rcmail.show_popup_dialog(body, title, buttons, {
                width: 560,
                height: 480,
                classes: { 'ui-dialog': 'webdav-files-dialog' },
                close: function () { 
                    console.log('Dialog closed');
                    
                    // For folder picker mode, try to update the field when dialog is closed
                    if (mode === 'folder' && folder_target) {
                        console.log('Folder picker dialog closed, trying to update field');
                        
                        // Check if we have a stored selected path
                        if (window.wdfSelectedFolders && window.wdfSelectedFolders[folder_target]) {
                            var selectedPath = window.wdfSelectedFolders[folder_target];
                            console.log('Using stored selected path:', selectedPath);
                            
                            // Try to find and update the field
                            var targetField = $('#' + folder_target);
                            if (!targetField.length) {
                                targetField = $('input.wdf-folder-input').first();
                                console.log('Using alternative field:', targetField.attr('id'));
                            }
                            
                            if (targetField.length) {
                                targetField.val(selectedPath);
                                targetField.trigger('change');
                                
                                // Visual feedback
                                targetField.css('background-color', '#ffffcc');
                                setTimeout(function() {
                                    targetField.css('background-color', '');
                                }, 1000);
                                
                                console.log('SUCCESS: Field updated on dialog close');
                            } else {
                                console.log('ERROR: Could not find field to update on dialog close');
                            }
                        }
                    }
                    
                    dialog = null; 
                }
            });
            
            if (dialog) {
                console.log('Popup dialog created successfully');
                
                // Check if dialog has the expected structure
                if (dialog.dialog) {
                    console.log('Dialog has dialog method');
                } else {
                    console.log('ERROR: dialog does not have dialog method');
                }
            } else {
                console.log('ERROR: Failed to create popup dialog');
            }
        } catch (e) {
            console.log('ERROR creating popup dialog:', e);
        }

        cur_path = '/';
        load_dir('/');
    }

    function load_dir(path) {
        if (pending) {
            return;
        }
        pending = true;

        if (dialog) {
            dialog.find('.wdf-list').html('<li class="wdf-loading">' + t('loading') + '</li>');
        }

        var lock = rcmail.set_busy(true, 'loading');
        rcmail.http_post('plugin.webdav_files.browse', { _path: path }, lock);
    }

    function on_browser_data(data) {
        pending = false;

        if (!dialog) {
            return;
        }

        cur_path = data.path || '/';
        render_breadcrumb(cur_path);

        var list = dialog.find('.wdf-list').empty(),
            picking_folder = mode === 'folder';

        if (cur_path !== '/') {
            list.append(
                row_icon('folder-up', '..', t('parentfolder'))
                    .on('click', function () { load_dir(parent_path(cur_path)); })
            );
        }

        if (!data.entries.length) {
            list.append('<li class="wdf-empty">' + t('emptyfolder') + '</li>');
        }

        $.each(data.entries, function (i, entry) {
            if (entry.folder) {
                // single click opens the folder
                list.append(
                    row_icon('folder', entry.name)
                        .data('path', entry.path)
                        .on('click', function () { load_dir(entry.path); })
                );
            } else if (!picking_folder) {
                list.append(
                    row_icon('file', entry.name, human_size(entry.size))
                        .data('path', entry.path)
                        .on('click', function () { toggle_select($(this)); })
                );
            }
        });
    }

    function row_icon(type, name, meta) {
        var li = $('<li>').addClass('wdf-item wdf-' + type);
        li.append($('<span class="wdf-icon">'));
        li.append($('<span class="wdf-name">').text(name));
        if (meta) {
            li.append($('<span class="wdf-meta">').text(meta));
        }
        return li;
    }

    function render_breadcrumb(path) {
        var crumb = dialog.find('.wdf-breadcrumb').empty(),
            parts = path.split('/').filter(Boolean),
            acc = '';

        crumb.append($('<a href="#">').text(t('home')).on('click', function (e) {
            e.preventDefault(); load_dir('/');
        }));

        $.each(parts, function (i, part) {
            acc += '/' + part;
            var p = acc;
            crumb.append(document.createTextNode(' / '));
            crumb.append($('<a href="#">').text(part).on('click', function (e) {
                e.preventDefault(); load_dir(p);
            }));
        });
    }

    function toggle_select(li) {
        li.toggleClass('selected');
    }

    function selected_paths() {
        var paths = [];
        dialog.find('.wdf-file.selected').each(function () {
            paths.push($(this).data('path'));
        });
        return paths;
    }

    function parent_path(path) {
        var p = path.replace(/\/+$/, '').split('/');
        p.pop();
        return p.join('/') || '/';
    }

    // ------------------------------------------------------------------
    // Actions from the browser
    // ------------------------------------------------------------------

    function submit_selection(which) {
        var paths = selected_paths();

        if (!paths.length) {
            rcmail.display_message(t('error_noselection'), 'warning');
            return;
        }

        if (which === 'attach') {
            var lock = rcmail.set_busy(true, 'webdav_files.attaching');
            rcmail.http_post('plugin.webdav_files.attach', {
                _paths: paths,
                _id: rcmail.env.compose_id
            }, lock);
        } else {
            var lock2 = rcmail.set_busy(true, 'webdav_files.creatinglink');
            rcmail.http_post('plugin.webdav_files.link', { _paths: paths }, lock2);
        }
    }

    function choose_folder() {
        // when picking a folder we use the folder currently open in the browser
        console.log('choose_folder called with target: ' + folder_target + ' and path: ' + cur_path);
        
        // Store the selected path globally
        window.wdfSelectedPath = cur_path;
        console.log('Stored selected path globally:', cur_path);
        
        // Method 1: If we have a direct reference to the field, update it now
        if (window.wdfTargetField) {
            var oldValue = window.wdfTargetField.val();
            window.wdfTargetField.val(cur_path);
            window.wdfTargetField.trigger('change');
            console.log('SUCCESS: Field updated via direct reference - from "' + oldValue + '" to "' + cur_path + '"');
            
            // Visual feedback
            window.wdfTargetField.css('background-color', '#ffffcc');
            setTimeout(function() {
                window.wdfTargetField.css('background-color', '');
            }, 1000);
        }
        
        // Method 2: If we have a reference to the save dialog's folder input, use it
        if (window.wdfFolderInputForSaveDialog) {
            var oldValue = window.wdfFolderInputForSaveDialog.val();
            window.wdfFolderInputForSaveDialog.val(cur_path);
            window.wdfFolderInputForSaveDialog.trigger('change');
            console.log('SUCCESS: Field updated via save dialog reference - from "' + oldValue + '" to "' + cur_path + '"');
            
            // Visual feedback
            window.wdfFolderInputForSaveDialog.css('background-color', '#ffffcc');
            setTimeout(function() {
                window.wdfFolderInputForSaveDialog.css('background-color', '');
            }, 1000);
            
            // This is our most reliable method, so we can consider this a success
            // and skip the other methods
            console.log('Using most reliable method - skipping other methods');
            
            // Close the dialog
            var dialogValid = dialog && dialog.dialog;
            if (dialogValid) {
                console.log('Closing dialog');
                try {
                    dialog.dialog('close');
                } catch (e) {
                    console.log('ERROR closing dialog:', e);
                }
            } else {
                console.log('WARNING: dialog or dialog.dialog is not defined, cannot close dialog');
            }
            
            return;
        }
        
        // Method 2: Try to find and update the field directly
        function setFolderField(targetId, value) {
            if (!targetId) {
                console.log('ERROR: No target ID provided');
                return false;
            }
            
            // Try direct ID first
            var targetField = $('#' + targetId);
            var success = false;
            
            if (targetField.length) {
                var oldValue = targetField.val();
                targetField.val(value);
                console.log('SUCCESS: Direct ID method - Selected folder changed from "' + oldValue + '" to "' + value + '" in field: ' + targetId);
                targetField.trigger('change');
                success = true;
            } else {
                console.log('ERROR: Target field not found by direct ID: ' + targetId);
                
                // Try to find any field with similar ID
                var possibleFields = $('[id*="' + targetId + '"]');
                if (possibleFields.length) {
                    console.log('Found possible matching fields:');
                    possibleFields.each(function() {
                        console.log('  Field id:', $(this).attr('id'), 'type:', $(this).prop('type'));
                    });
                    
                    // Try the first one
                    targetField = possibleFields.first();
                    var oldValue = targetField.val();
                    targetField.val(value);
                    console.log('SUCCESS: Partial ID match - Selected folder changed from "' + oldValue + '" to "' + value + '" in field: ' + targetField.attr('id'));
                    targetField.trigger('change');
                    success = true;
                }
            }
            
            // If still not found, try by class
            if (!success) {
                var folderInputs = $('input.wdf-folder-input');
                if (folderInputs.length) {
                    folderInputs.each(function() {
                        var field = $(this);
                        var oldValue = field.val();
                        field.val(value);
                        console.log('SUCCESS: Class method - Set folder from "' + oldValue + '" to "' + value + '" in field with class wdf-folder-input');
                        field.trigger('change');
                        success = true;
                        
                        // Stop after the first one
                        return false;
                    });
                }
            }
            
            if (success) {
                // Visual feedback
                targetField.css('background-color', '#ffffcc');
                setTimeout(function() {
                    targetField.css('background-color', '');
                }, 1000);
            }
            
            return success;
        }
        
        // Try the direct method
        var success = setFolderField(folder_target, cur_path);
        
        if (!success) {
            console.log('ERROR: All direct methods failed to update the folder field');
        }
        
        // Method 3: Trigger a custom window event
        console.log('Triggering window event wdf-folder-selected');
        try {
            $(window).trigger('wdf-folder-selected', [cur_path]);
        } catch (e) {
            console.log('ERROR triggering window event:', e);
        }
        
        // Method 4: Store the selected folder in a global variable that can be accessed by other functions
        if (!window.wdfSelectedFolders) {
            window.wdfSelectedFolders = {};
        }
        window.wdfSelectedFolders[folder_target] = cur_path;
        console.log('Stored selected folder in global variable:', folder_target, '=', cur_path);
        
        // Method 5: Use the callback if it exists
        if (window.wdfUpdateFolderField) {
            console.log('Using update callback method');
            try {
                window.wdfUpdateFolderField(cur_path);
            } catch (e) {
                console.log('ERROR in update callback:', e);
            }
        }
        
        // Close the dialog
        var dialogValid = dialog && dialog.dialog;
        console.log('Dialog valid:', dialogValid);
        
        if (dialogValid) {
            console.log('Closing dialog');
            try {
                dialog.dialog('close');
            } catch (e) {
                console.log('ERROR closing dialog:', e);
            }
        } else {
            console.log('WARNING: dialog or dialog.dialog is not defined, cannot close dialog');
        }
    }

    function new_folder() {
        var name = window.prompt(t('newfolder_prompt'), '');
        if (name === null) {
            return;
        }
        name = $.trim(name);
        if (!name) {
            return;
        }

        var lock = rcmail.set_busy(true, 'loading');
        rcmail.http_post('plugin.webdav_files.mkdir', { _path: cur_path, _name: name }, lock);
    }

    function on_attached(data) {
        (data.attachments || []).forEach(add_attachment_row);

        if (data.errors && data.errors.length) {
            rcmail.display_message(t('error_someattach') + ' ' + data.errors.join(', '), 'warning');
        } else if (data.attachments && data.attachments.length) {
            rcmail.display_message(t('attached_ok'), 'confirmation');
        }

        if (dialog) {
            dialog.dialog('close');
        }
    }

    // Render an attachment row the same way the core upload code does, so the
    // file integrates with the normal "remove attachment" control.
    function add_attachment_row(att) {
        if (!rcmail.gui_objects || !rcmail.gui_objects.attachmentlist) {
            return;
        }

        var fid = 'rcmfile' + att.id,
            name = att_escape(att.name),
            del = rcmail.env.compose_mode || true,
            html = '<a class="filename">'
                + '<span class="attachment-name">' + name + '</span>'
                + ' <span class="attachment-size">(' + att.size + ')</span>'
                + '</a>'
                + '<a href="#delete" class="delete" title="' + att_escape(t('cancel')) + '" '
                + 'onclick="return rcmail.command(\'remove-attachment\',\'' + fid + '\',this,event)">'
                + '<span class="inner">' + att_escape(t('cancel')) + '</span></a>';

        rcmail.add2attachment_list(fid, {
            name: att.name,
            html: html,
            classname: 'webdav-attachment',
            complete: true,
            mimetype: att.mimetype,
            aid: att.id
        }, fid);
    }

    function att_escape(s) {
        return $('<div>').text(s == null ? '' : s).html();
    }

    function on_links(data) {
        var links = data.links || [];

        if (links.length) {
            insert_links(links);
            rcmail.display_message(t('link_ok'), 'confirmation');
        }

        if (data.errors && data.errors.length) {
            rcmail.display_message(t('error_somelink') + ' ' + data.errors.join(', '), 'warning');
        }

        if (dialog) {
            dialog.dialog('close');
        }
    }

    function insert_links(links) {
        if (rcmail.editor && rcmail.editor.editor) {
            var html = links.map(function (l) {
                return '<a href="' + att_escape(l.url) + '">' + att_escape(l.name) + '</a>';
            }).join('<br>');
            rcmail.editor.editor.execCommand('mceInsertContent', false, html + '<br>');
            return;
        }

        var el = document.getElementById('composebody');
        if (!el) {
            return;
        }

        var text = links.map(function (l) { return l.name + ': ' + l.url; }).join('\n') + '\n';
        var start = el.selectionStart != null ? el.selectionStart : el.value.length;
        el.value = el.value.substring(0, start) + text + el.value.substring(start);
        $(el).trigger('change');
    }

    // ------------------------------------------------------------------
    // Save attachments to cloud (message view / list)
    // ------------------------------------------------------------------

    function open_save_attachments() {
        if (!configured()) {
            return not_configured();
        }
        var ref = current_message_ref();
        if (!ref) {
            return;
        }

        var lock = rcmail.set_busy(true, 'loading');
        rcmail.http_post('plugin.webdav_files.list_attachments', ref, lock);
    }

    function on_attachment_list(data) {
        var atts = data.attachments || [];

        if (!atts.length) {
            rcmail.display_message(t('error_noattachments'), 'warning');
            return;
        }

        var body = $('<div class="wdf-save-dialog">');
        body.append($('<p>').text(t('saveattach_intro')));

        var list = $('<ul class="wdf-checklist">');
        atts.forEach(function (a) {
            var item = $('<li>').append(
                $('<label>').append(
                    $('<input type="checkbox" checked>').val(a.mime_id)
                ).append($('<span class="wdf-name">').text(a.name))
                 .append($('<span class="wdf-meta">').text('(' + a.size + ')'))
            );
            
            // Add filename input field for each attachment
            var filenameInput = $('<input type="text" class="wdf-filename-input">')
                .attr('placeholder', t('filename'))
                .val(a.name.replace(/\.[^/.]+$/, '')) // Remove extension to allow user to change base name
                .data('mime-id', a.mime_id);
                
            item.append($('<div>').addClass('wdf-filename-container').append(filenameInput));
            list.append(item);
        });
        body.append(list);

        body.append(folder_field('wdf-save-folder', conf().attach_folder || '/'));

        var ref = current_message_ref();
        
        // Store a reference to the folder input for later use
        var folderInput = body.find('#wdf-save-folder');
        console.log('Folder input found:', folderInput.length > 0);
        
        // If we found the folder input, store a reference to it
        if (folderInput.length) {
            window.wdfFolderInputForSaveDialog = folderInput;
            console.log('Stored reference to folder input for save dialog');
        }

        rcmail.show_popup_dialog(body, t('saveattach_title'), [
            {
                text: t('save'), 'class': 'mainaction save',
                click: function () {
                    var parts = [];
                    var filenames = {};
                    
                    // Collect selected attachments and their filenames
                    body.find('input[type=checkbox]:checked').each(function () {
                        var checkbox = this;
                        var mimeId = this.value;
                        parts.push(mimeId);
                        
                        // Find the filename input for this specific attachment using DOM traversal
                        var listItem = $(this).closest('li');
                        var filenameInput = listItem.find('.wdf-filename-input');
                        
                        if (filenameInput.length) {
                            var customName = filenameInput.val().trim();
                            if (customName) {
                                // Preserve original extension if not provided
                                var originalName = listItem.find('.wdf-name').text();
                                var ext = originalName.split('.').pop();
                                if (customName.indexOf('.') === -1 && ext) {
                                    customName += '.' + ext;
                                }
                                filenames[mimeId] = customName;
                                
                                // Debug logging
                                console.log('Using custom filename for mimeId ' + mimeId + ': ' + customName);
                            }
                        } else {
                            // Debug logging
                            console.log('No filename input found for mimeId ' + mimeId);
                        }
                    });
                    
                    if (!parts.length) {
                        rcmail.display_message(t('error_noselection'), 'warning');
                        return;
                    }
                    
                    var p = $.extend({}, ref);
                    p._parts = parts;
                    p._folder = body.find('#wdf-save-folder').val();
                    p._filenames = filenames;
                    
                    console.log('Saving attachments to folder: ' + p._folder);
                    console.log('Parts:', parts);
                    console.log('Filenames:', filenames);
                    
                    // Also log all input fields to make sure they're correctly identified
                    console.log('All filename inputs:');
                    body.find('.wdf-filename-input').each(function() {
                        console.log('ID:', $(this).data('mime-id'), 'Value:', $(this).val());
                    });
                    
                    var lock = rcmail.set_busy(true, 'webdav_files.saving');
                    rcmail.http_post('plugin.webdav_files.save_attachments', p, lock);
                    $(this).dialog('close');
                }
            },
            { text: t('cancel'), 'class': 'cancel', click: function () { 
                // Before closing, check if we need to update the folder field
                if (window.wdfSelectedPath && window.wdfFolderInputForSaveDialog) {
                    console.log('Updating folder field before closing save dialog');
                    window.wdfFolderInputForSaveDialog.val(window.wdfSelectedPath);
                    window.wdfFolderInputForSaveDialog.trigger('change');
                    
                    // Visual feedback
                    window.wdfFolderInputForSaveDialog.css('background-color', '#ffffcc');
                    setTimeout(function() {
                        window.wdfFolderInputForSaveDialog.css('background-color', '');
                    }, 1000);
                }
                
                $(this).dialog('close'); 
            } }
        ], { width: 500 });
    }

    function on_saved(data) {
        var saved = (data.saved || []).length;
        if (saved) {
            rcmail.display_message(t('saved_ok').replace('$n', saved).replace('$folder', data.folder), 'confirmation');
        }
        if (data.errors && data.errors.length) {
            rcmail.display_message(t('error_somesaved') + ' ' + data.errors.join(', '), 'warning');
        }
    }

    // ------------------------------------------------------------------
    // Save PDF print to cloud (message view / list)
    // ------------------------------------------------------------------

    function save_pdf() {
        if (!configured()) {
            return not_configured();
        }
        var ref = current_message_ref();
        if (!ref) {
            return;
        }

        var body = $('<div class="wdf-save-dialog">');
        body.append($('<p>').text(t('savepdf_intro')));

        body.append(
            $('<div class="form-group">')
                .append($('<label for="wdf-pdf-name">').text(t('filename')))
                .append($('<input type="text" id="wdf-pdf-name" class="form-control">'))
        );

        body.append(folder_field('wdf-pdf-folder-input', conf().pdf_folder));

        rcmail.show_popup_dialog(body, t('savepdf_title'), [
            {
                text: t('save'), 'class': 'mainaction save',
                click: function () {
                    var p = $.extend({}, ref);
                    p._folder = body.find('#wdf-pdf-folder-input').val();
                    p._filename = body.find('#wdf-pdf-name').val();
                    var lock = rcmail.set_busy(true, 'webdav_files.savingpdf');
                    rcmail.http_post('plugin.webdav_files.save_pdf', p, lock);
                    $(this).dialog('close');
                }
            },
            { text: t('cancel'), 'class': 'cancel', click: function () { $(this).dialog('close'); } }
        ], { width: 480 });
    }

    function on_pdf_saved(data) {
        rcmail.display_message(t('pdf_saved_ok').replace('$file', data.file).replace('$folder', data.folder), 'confirmation');
    }

    // A folder input with an inline "Browse" button that opens the picker.
    function folder_field(id, value) {
        var input = $('<input type="text" class="form-control wdf-folder-input">')
            .attr('id', id)
            .val(value || '/');

        var browse = $('<button type="button" class="button wdf-browse">')
            .text(t('browse'))
            .on('click', function () { open_inline_folder_picker(id); });

        return $('<div class="form-group wdf-folder-group">')
            .append($('<label>').attr('for', id).text(t('targetfolder')))
            .append($('<div class="wdf-folder-row">').append(input).append(browse));
    }

    function open_inline_folder_picker(target) {
        if (!configured()) {
            return not_configured();
        }
        mode = 'folder';
        folder_target = target;
        
        // Enhanced debugging - store current state for later comparison
        console.log('open_inline_folder_picker called with target:', target);
        
        // Try to get the target field right away
        var targetField = $('#' + target);
        var originalValue = '';
        
        if (targetField.length) {
            originalValue = targetField.val();
            console.log('Current value of target field:', originalValue);
            
            // If we found the field, store a direct reference to it
            // This is the most reliable way to update it later
            window.wdfTargetField = targetField;
            window.wdfTargetId = target;
            console.log('Stored direct reference to target field');
        } else {
            console.log('ERROR: Target field not found when opening picker!');
            
            // Store the target ID for later use
            window.wdfTargetId = target;
            
            // Create a callback function that will try to find the field later
            window.wdfUpdateFolderField = function(selectedPath) {
                console.log('Update callback called with path:', selectedPath);
                
                // Try multiple ways to find and update the field
                var field = $('#' + target);
                if (!field.length) {
                    field = $('input.wdf-folder-input').first();
                    console.log('Using alternative field:', field.attr('id'));
                }
                
                if (field.length) {
                    field.val(selectedPath);
                    field.trigger('change');
                    console.log('SUCCESS: Field updated via callback');
                    
                    // Visual feedback
                    field.css('background-color', '#ffffcc');
                    setTimeout(function() {
                        field.css('background-color', '');
                    }, 1000);
                } else {
                    console.log('ERROR: Could not find field to update via callback');
                }
            };
        }
        
        // Store the selected path for later use
        window.wdfSelectedPath = null;
        
        // Add an event listener to handle the folder selection
        $(window).one('wdf-folder-selected', function(e, selectedPath) {
            console.log('Window event received with path:', selectedPath);
            
            // Store the selected path
            window.wdfSelectedPath = selectedPath;
            
            // If we have a direct reference to the field, update it now
            if (window.wdfTargetField) {
                window.wdfTargetField.val(selectedPath);
                window.wdfTargetField.trigger('change');
                console.log('SUCCESS: Field updated via direct reference');
                
                // Visual feedback
                window.wdfTargetField.css('background-color', '#ffffcc');
                setTimeout(function() {
                    window.wdfTargetField.css('background-color', '');
                }, 1000);
            } else if (window.wdfUpdateFolderField) {
                window.wdfUpdateFolderField(selectedPath);
            }
        });
        
        open_browser(t('pickfolder_title'), [
            { text: t('selectfolder'), 'class': 'mainaction select', click: choose_folder },
            { text: t('cancel'), 'class': 'cancel', click: function () { dialog.dialog('close'); } }
        ], false);
    }

    // ------------------------------------------------------------------
    // misc
    // ------------------------------------------------------------------

    function on_error(data) {
        pending = false;
        rcmail.display_message((data && data.message) || t('error_generic'), 'error');
    }

    function human_size(bytes) {
        bytes = parseInt(bytes, 10) || 0;
        var units = ['B', 'KB', 'MB', 'GB'], i = 0;
        while (bytes >= 1024 && i < units.length - 1) { bytes /= 1024; i++; }
        return (i === 0 ? bytes : bytes.toFixed(1)) + ' ' + units[i];
    }
})();
