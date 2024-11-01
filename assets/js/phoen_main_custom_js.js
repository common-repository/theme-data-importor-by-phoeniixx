var demo_contents_import_running = false;
var demo_contents_iframe_running = false;
window.onbeforeunload = function() {
    if ( demo_contents_import_running ) {
        return phoen_data_variable_arguments.confirm_leave;
    }
};



// -------------------------------------------------------------------------------
var demo_contents_working_plugins = window.demo_contents_working_plugins || {};
var demo_contents_viewing_theme = window.demo_contents_viewing_theme || {};

(function ( $ ) {

    var phoen_data_variable_arguments = phoen_data_variable_arguments || window.phoen_data_variable_arguments;

    if( typeof phoen_data_variable_arguments.plugins.activate !== "object" ) {
        phoen_data_variable_arguments.plugins.activate = {};
    }
    var $document = $( document );

    /**
     * Function that loads the Mustache template
     */
    var repeaterTemplate = _.memoize(function () {
        var compiled,
            /*
             * Underscore's default ERB-style templates are incompatible with PHP
             * when asp_tags is enabled, so WordPress uses Mustache-inspired templating syntax.
             *
             * @see track ticket #22344.
             */
            options = {
                evaluate: /<#([\s\S]+?)#>/g,
                interpolate: /\{\{\{([\s\S]+?)\}\}\}/g,
                escape: /\{\{([^\}]+?)\}\}(?!\})/g,
                variable: 'data'
            };

        return function (data, tplId ) {
            if ( typeof tplId === "undefined" ) {
                tplId = '#tmpl-phoen-theme-data-importer--preview';
            }
            compiled = _.template(jQuery( tplId ).html(), null, options);
            return compiled(data);
        };
    });


    String.prototype.format = function() {
        var newStr = this, i = 0;
        while (/%s/.test(newStr)) {
            newStr = newStr.replace("%s", arguments[i++]);
        }
        return newStr;
    };

    var template = repeaterTemplate();

    var phoenImporterAllData  = {
        plugins: {
            install: {},
            all: {},
            activate: {}
        },

        phoen_load_min: function(){
            demo_contents_import_running = true;
            demo_contents_iframe_running = null;
            $( '#demo_contents_iframe_running' ).remove();
            var frame = $( '<iframe id="demo_contents_iframe_running" style="display: none;"></iframe>' );
            frame.appendTo('body');
            var doc;
            // Thanks http://jsfiddle.net/KSXkS/1/
            try { // simply checking may throw in ie8 under ssl or mismatched protocol
                doc = frame[0].contentDocument ? frame[0].contentDocument : frame[0].document;
            } catch(err) {
                doc = frame[0].document;
            }
            doc.open();
            // doc.close();
        },

        end_loading: function(){
             $( '#demo_contents_iframe_running' ).remove();
            demo_contents_import_running = false;
        },

        loading_step: function( $element ){
            $element.removeClass( 'phoen-theme-data-importer--waiting phoen-theme-data-importer--running' );
            $element.addClass( 'phoen-theme-data-importer--running' );
        },
        completed_step: function( $element, event_trigger ){
            $element.removeClass( 'phoen-theme-data-importer--running phoen-theme-data-importer--waiting' ).addClass( 'phoen-theme-data-importer--completed' );
            if ( typeof event_trigger !== "undefined" ) {
                $document.trigger( event_trigger );
            }
        },
        preparing_plugins: function( plugins ) {
            var that = this;
            if ( typeof plugins === "undefined" ) {
                plugins = phoen_data_variable_arguments.plugins;
            }
            plugins = _.defaults( plugins,  {
                install: {},
                all: {},
                activate: {}
            } );

            demo_contents_working_plugins = plugins;
            that.plugins = demo_contents_working_plugins;

            var $list_install_plugins = $('.phoen-theme-data-importer-install-plugins');
            var n = _.size(that.plugins.all);
            if (n > 0) {
                var $child_steps = $list_install_plugins.find('.phoen-theme-data-importer--child-steps');
                $.each( that.plugins.all, function ($slug, plugin) {
                    var msg = plugin.name;

                    if( typeof that.plugins.install[ $slug] !== "undefined" ) {
                        msg = phoen_data_variable_arguments.messages.plugin_not_installed.format( plugin.name );
                    } else {
                        if( typeof that.plugins.activate[ $slug] !== "undefined" ) {
                            msg = phoen_data_variable_arguments.messages.plugin_not_activated.format( plugin.name );
                        }
                    }

                    var $item = $('<div data-slug="' + $slug + '" class="phoen-theme-data-importer-child-item dc-unknown-status phoen-theme-data-importer-plugin-' + $slug + '">'+msg+'</div>');
                    $child_steps.append($item);
                    $item.attr('data-plugin', $slug);
                });
            } else {
                $list_install_plugins.hide();
            }


            if ( demo_contents_viewing_theme.activate ) {
                $( '.phoen-theme-data-importer--activate-notice' ).hide();
            } else {
                $( '.phoen-theme-data-importer-import-progress' ).hide();
                $( '.phoen-theme-data-importer--activate-notice' ).show();

                var activate_theme_btn =  $( '<a href="#" class="phoen-theme-data-importer--activate-now button button-primary">'+phoen_data_variable_arguments.activate_theme+'</a>' );
                $( '.phoen-theme-data-importer--import-now' ).replaceWith( activate_theme_btn );
            }

        },
        phoen_install_Plugins: function() {
            var that = this;
            that.plugins = demo_contents_working_plugins;
            // Install Plugins
            var $list_install_plugins = $( '.phoen-theme-data-importer-install-plugins' );
            that.loading_step( $list_install_plugins );
            console.log( 'Being installing plugins....' );
            var $child_steps = $list_install_plugins.find(  '.phoen-theme-data-importer--child-steps' );
            var n = _.size( that.plugins.install );
            if ( n > 0 ) {

                var callback = function( current ){
                    if ( current.length ) {
                        var slug = current.attr( 'data-plugin' );
                        if ( typeof that.plugins.install[ slug ] === "undefined" ) {
                            var next = current.next();
                            callback( next );
                        } else {
                            var plugin =  that.plugins.install[ slug ];
                            var msg = phoen_data_variable_arguments.messages.plugin_installing.format( plugin.name );
                            console.log( msg );
                            current.html( msg );

                            $.post( plugin.page_url, plugin.args, function (res) {
                                //console.log(plugin.name + ' Install Completed');
                                plugin.args.action = phoen_data_variable_arguments.action_active_plugin;
                                that.plugins.activate[ slug ] = plugin;
                                var msg = phoen_data_variable_arguments.messages.plugin_installed.format( plugin.name );
                                console.log( msg );
                                current.html( msg );
                                var next = current.next();
                                callback( next );
                            }).fail(function() {
                                demo_contents_working_plugins = that.plugins;
                                console.log( 'Plugins install failed' );
                                $document.trigger( 'demo_contents_plugins_install_completed' );
                            });
                        }
                    } else {
                        demo_contents_working_plugins = that.plugins;
                        console.log( 'Plugins install completed' );
                        $document.trigger( 'demo_contents_plugins_install_completed' );
                    }
                };

                var current = $child_steps.find( '.phoen-theme-data-importer-child-item' ).eq( 0 );
                callback( current );
            } else {
                demo_contents_working_plugins = that.plugins;
                console.log( 'Plugins install completed - 0' );
                //$list_install_plugins.hide();
                $document.trigger( 'demo_contents_plugins_install_completed' );
            }

            // that.completed_step( $list_install_plugins, 'demo_contents_plugins_install_completed' );

        },
        phoen_active_plugin: function(){
            var that = this;
            that.plugins = demo_contents_working_plugins;

            that.plugins.activate = $.extend({},that.plugins.activate );
            console.log( 'phoen_active_plugin', that.plugins );
            var $list_active_plugins = $( '.phoen-theme-data-importer-install-plugins' );
            that.loading_step( $list_active_plugins );
            var $child_steps = $list_active_plugins.find(  '.phoen-theme-data-importer--child-steps' );
            var n = _.size( that.plugins.activate );
            console.log( 'Being activate plugins....' );
            if (  n > 0 ) {
                var callback = function (current) {
                    if (current.length) {
                        var slug = current.attr('data-plugin');

                        if ( typeof that.plugins.activate[ slug ] === "undefined" ) {
                            var next = current.next();
                            callback( next );
                        } else {
                            var plugin = that.plugins.activate[slug];
                            var msg = phoen_data_variable_arguments.messages.plugin_activating.format( plugin.name );
                            console.log( msg );
                            current.html( msg );
                            $.post(plugin.page_url, plugin.args, function (res) {

                                var msg = phoen_data_variable_arguments.messages.plugin_activated.format( plugin.name );
                                console.log( msg );
                                current.html( msg );
                                var next = current.next();
                                callback(next);
                            }).fail(function() {
                                console.log( 'Plugins activate failed' );
                                that.completed_step( $list_active_plugins, 'demo_contents_plugins_active_completed' );
                            });
                        }

                    } else {
                        console.log(' Activated all plugins');
                        that.completed_step( $list_active_plugins, 'demo_contents_plugins_active_completed' );
                    }
                };

                var current = $child_steps.find( '.phoen-theme-data-importer-child-item' ).eq( 0 );
                callback( current );

            } else {
               // $list_active_plugins.hide();
                console.log(' Activated all plugins - 0');
                $list_active_plugins.removeClass('phoen-theme-data-importer--running phoen-theme-data-importer--waiting').addClass('phoen-theme-data-importer--completed');
                $document.trigger('demo_contents_plugins_active_completed');
            }

        },
        ajax: function( doing, complete_cb, fail_cb ){
            //console.log( 'Being....', doing );
            $.ajax( {
                url: phoen_data_variable_arguments.ajaxurl,
                data: {
                    //action: 'demo_contents__import',
					action: 'phoen_data_ajax_func',
                    doing: doing,
                    current_theme: demo_contents_viewing_theme,
                    theme: '', // Import demo for theme ?
                    version: '' // Current demo version ?
                },
                type: 'GET',
                dataType: 'json',
                success: function( res ){
                    //console.log( res );
					
                    if ( typeof complete_cb === 'function' ) {
                        complete_cb( res );
                    }
                    console.log( 'Completed: '+ doing, res );
                    $document.trigger( 'demo_contents_'+doing+'_completed' );
                },
                fail: function( res ){
					
                    if ( typeof fail_cb === 'function' ) {
                        fail_cb( res );
                    }
                    //console.log( 'Failed: '+ doing, res );
                    $document.trigger( 'demo_contents_'+doing+'_failed' );
                    $document.trigger( 'demo_contents_ajax_failed', [ doing ] );
                },error: function ( xhr ) {
					
					$.cookie("phoen_cookie_reload", 225);
					
					alert("Reload your page because your execution time is expired! ");
					
					window.location.reload(true);
				}

            } )
        },
        phoen_users_import: function(){
            var step =  $( '.phoen-theme-data-importer-import-users' );
            var that = this;
            that.loading_step( step );
            this.ajax( 'phoen_users_import', function(){
                that.completed_step( step );
            } );
        },
        phoen_categories_import: function(){
            var step =  $( '.phoen-theme-data-importer-import-categories' );
            var that = this;
            that.loading_step( step );
            this.ajax(  'phoen_categories_import', function(){
                that.completed_step( step );
            } );
        },
        phoen_tags_import: function(){
            var step =  $( '.phoen-theme-data-importer-import-tags' );
            var that = this;
            that.loading_step( step );
            this.ajax(  'phoen_tags_import', function(){
                that.completed_step( step );
            } );
        },
        phoen_taxs_import: function(){
            var step =  $( '.phoen-theme-data-importer-import-taxs' );
            var that = this;
            that.loading_step( step );
            this.ajax(  'phoen_taxs_import', function(){
                that.completed_step( step );
            } );
        },
        phoen_posts_import: function(){
            var step =  $( '.phoen-theme-data-importer-import-posts' );
            var that = this;
            that.loading_step( step );
            this.ajax( 'phoen_posts_import', function(){
                that.completed_step( step );
            } );
        },

        phoen_theme_option: function(){
            var step =  $( '.phoen-theme-data-importer-import-theme-options' );
            var that = this;
            that.loading_step( step );
            this.ajax( 'phoen_theme_option', function(){
                that.completed_step( step );
            } );
        },

        phoen_widgets_import: function(){
            var step =  $( '.phoen-theme-data-importer-import-widgets' );
            var that = this;
            that.loading_step( step );
            this.ajax( 'phoen_widgets_import', function(){
                that.completed_step( step );
            } );
        },

        phoen_customize_import: function(){
            var step =  $( '.phoen-theme-data-importer-import-customize' );
            var that = this;
            that.loading_step( step );
            this.ajax( 'phoen_customize_import', function (){
                that.completed_step( step );
            } );
        },

        toggle_collapse: function(){
            $document .on( 'click', '.phoen-theme-data-importer-collapse-sidebar', function( e ){
                $( '#phoen-theme-data-importer--preview' ).toggleClass('ft-preview-collapse');
            } );
        },

        done: function(){
            console.log( 'All done' );
            this.end_loading();
            $( '.phoen-theme-data-importer--import-now' ).replaceWith( '<a href="'+phoen_data_variable_arguments.home+'" class="button button-primary">'+phoen_data_variable_arguments.btn_done_label+'</a>' );
        },

        failed: function(){
            console.log( 'Import failed' );
            $( '.phoen-theme-data-importer--import-now' ).replaceWith( '<span class="button button-secondary">'+phoen_data_variable_arguments.failed_msg+'</span>' );
        },

        preview: function(){
            var that = this;
            $document .on( 'click', '.phoen-theme-data-importer-themes-listing .theme', function( e ){
                e.preventDefault();
                console.log( 'ok' );
                var t               = $( this );
                var btn             = $( '.phoen-theme-data-importer--preview-theme-btn', t );
                var theme           = btn.closest('.theme');
                var slug            = btn.attr( 'data-theme-slug' ) || '';
                var name            = btn.attr( 'data-name' ) || '';
                var demo_version    = btn.attr( 'data-demo-version' ) || '';
                var demo_name       = btn.attr( 'data-demo-version-name' ) || '';
                var demo_url        = btn.attr( 'data-demo-url' ) || '';
                var img             = $( '.theme-screenshot img', theme ).attr( 'src' );
                if ( demo_url.indexOf( 'http' ) !== 0 ) {
                    demo_url = 'http://crazefree.phoeniixxdemo.com/';
                }
                $( '#phoen-theme-data-importer--preview' ).remove();

                demo_contents_viewing_theme =  {
                    name: name,
                    slug: slug,
                    demo_version: demo_version,
                    demo_name:  demo_name,
                    demoURL: demo_url,
                    img: img,
                    activate: false
                };

                if ( typeof phoen_data_variable_arguments.installed_themes[ slug ] !== "undefined" ) {
                    if ( phoen_data_variable_arguments.installed_themes[ slug ].activate ) {
                        demo_contents_viewing_theme.activate = true;
                    }
                }

                var previewHtml = template( demo_contents_viewing_theme );
                $( 'body' ).append( previewHtml );
                $( 'body' ).addClass( 'phoen-theme-data-importer-body-viewing' );

                that.preparing_plugins();

                $document.trigger( 'phoen_demo_show_screen_panel' );

            } );

            $document.on( 'click', '.phoen-theme-data-importer-close', function( e ) {
                e.preventDefault();
                if ( demo_contents_import_running ) {
                    var c = confirm( phoen_data_variable_arguments.confirm_leave ) ;
                    if ( c ) {
                        demo_contents_import_running = false;
                        $( this ).closest('#phoen-theme-data-importer--preview').remove();
                        $( 'body' ).removeClass( 'phoen-theme-data-importer-body-viewing' );
                    }
                } else {
                    $( this ).closest('#phoen-theme-data-importer--preview').remove();
                    $( 'body' ).removeClass( 'phoen-theme-data-importer-body-viewing' );
                }

            } );

        },

        phoen_all_cloud_files: function(){
            var that = this;
            var button = $( '.phoen-theme-data-importer--import-now, .phoen-theme-data-importer--activate-now' );
            button.html( phoen_data_variable_arguments.checking_resource );
            button.addClass( 'updating-message' );
            button.addClass( 'disabled' );
            that.ajax( 'phoen_all_cloud_files', function( res ){
                if ( res.success ) {
                    button.removeClass( 'disabled' );
                    button.removeClass( 'updating-message' );
                    if ( demo_contents_viewing_theme.activate ) {
                        button.html( phoen_data_variable_arguments.import_now );
                    } else {
                        button.html( phoen_data_variable_arguments.activate_theme );
                    }
                } else {
                    $( '.phoen-theme-data-importer--activate-notice.resources-not-found' ).show().removeClass( 'phoen-theme-data-importer-hide' );
                    $( '.phoen-theme-data-importer--activate-notice.resources-not-found .phoen-theme-data-importer--msg' ).addClass('not-found-data').show().html( res.data );
                    $( '.phoen-theme-data-importer-import-progress' ).hide();
                    var text = demo_contents_viewing_theme.activate ? phoen_data_variable_arguments.import_now : phoen_data_variable_arguments.activate_theme;
                    button.replaceWith( '<a href="#" class="phoen-theme-data-importer--no-data-btn button button-secondary disabled disable">'+text+'</a>' );
                }
            } );
        },

        init: function(){
            var that = this;

            that.preview();
            that.toggle_collapse();

            $document.on( 'demo_contents_ready', function(){
                $( '.phoen-theme-data-importer--activate-notice.resources-not-found ').slideUp(200).addClass( 'content-demos-hide' );
                that.phoen_load_min();
                that.phoen_install_Plugins();
            } );

             $document.on( 'demo_contents_plugins_install_completed', function(){
                that.phoen_active_plugin();
            } );

            $document.on( 'demo_contents_plugins_active_completed', function(){
                that.phoen_users_import();
            } );

            $document.on( 'demo_contents_phoen_users_import_completed', function(){
                that.phoen_categories_import();
            } );

            $document.on( 'demo_contents_phoen_categories_import_completed', function(){
                that.phoen_tags_import();
            } );

            $document.on( 'demo_contents_phoen_tags_import_completed', function(){
                that.phoen_taxs_import();
            } );

            $document.on( 'demo_contents_phoen_taxs_import_completed', function(){
                that.phoen_posts_import();
            } );

            $document.on( 'demo_contents_phoen_posts_import_completed', function(){
                that.phoen_theme_option();
            } );

            $document.on( 'demo_contents_phoen_theme_option_completed', function(){
                that.phoen_widgets_import();
            } );

            $document.on( 'demo_contents_phoen_widgets_import_completed', function(){
                that.phoen_customize_import();
            } );

            $document.on( 'demo_contents_phoen_customize_import_completed', function(){
                that.done();
            } );

            $document.on( 'demo_contents_ajax_failed', function(){
                that.failed();
            } );


            // Toggle Heading
            $document.on( 'click', '.phoen-theme-data-importer--step', function( e ){
                e.preventDefault();
                $( '.phoen-theme-data-importer--child-steps', $( this ) ).toggleClass( 'phoen-theme-data-importer--show' );
            } );

            // Import now click
            $document.on( 'click', '.phoen-theme-data-importer--import-now', function( e ) {
                e.preventDefault();
                if ( ! $( this ).hasClass( 'updating-message' ) ) {
                    $( this ).addClass( 'updating-message' );
                    $( this ).html( phoen_data_variable_arguments.importing );
                    $document.trigger( 'demo_contents_ready' );
                }
            } );


            // Activate Theme Click
            $document.on( 'click', '.phoen-theme-data-importer--activate-now', function( e ) {
                e.preventDefault();
                var btn =  $( this );
                if ( ! btn.hasClass( 'updating-message' ) ) {
                    btn.addClass( 'updating-message' );
                    that.ajax( 'activate_theme', function( res ){
                        var new_btn = $( '<a href="#" class="phoen-theme-data-importer--checking-resource  updating-message button button-primary">' + phoen_data_variable_arguments.checking_theme + '</a>' );
                        btn.replaceWith( new_btn );

                        phoen_data_variable_arguments.current_theme = demo_contents_viewing_theme.slug;
                        phoen_data_variable_arguments.current_child_theme =  demo_contents_viewing_theme.slug;

                        $.get( phoen_data_variable_arguments.theme_url, { __checking_plugins: 1 }, function( res ){
                            console.log( 'Checking plugin completed: ', phoen_data_variable_arguments.import_now );
                            $( '.phoen-theme-data-importer--checking-resource, .phoen-theme-data-importer--activate-now' ).replaceWith('<a href="#" class="phoen-theme-data-importer--import-now button button-primary">' + phoen_data_variable_arguments.import_now + '</a>');
                            if ( res.success ) {
                                demo_contents_viewing_theme.activate = true;
                                that.preparing_plugins( res.data );
                                $( '.phoen-theme-data-importer--activate-notice' ).slideUp( 200 );
                                $( '.phoen-theme-data-importer-import-progress' ).slideDown(200);
                            }
                        } );

                    } );
                }
            } );

            $document.on( 'phoen_demo_show_screen_panel', function(){
                //  that.phoen_load_min();
                that.phoen_all_cloud_files();
                //$document.trigger( 'demo_contents_phoen_theme_option_completed' );
                //$document.trigger( 'demo_contents_phoen_widgets_import_completed' );
            } );


            // Custom upload demo file
            var Media = wp.media({
                title: wp.media.view.l10n.addMedia,
                multiple: false,
               // library:
            });

            that.uploading_file = false;

            $document.on( 'click', '.phoen-theme-data-importer--upload-xml', function(e){
                e.preventDefault();
                Media.open();
                that.uploading_file = 'xml';
            } );

            $document.on( 'click', '.phoen-theme-data-importer--upload-json', function(e){
                e.preventDefault();
                Media.open();
                that.uploading_file = 'json';
            } );

            var check_upload = function(){
                if ( typeof  demo_contents_viewing_theme.xml_id !== "undefined"
                    &&typeof  demo_contents_viewing_theme.json_id !== "undefined"
                    && demo_contents_viewing_theme.xml_id
                    && demo_contents_viewing_theme.json_id
                ) {
                    if ( demo_contents_viewing_theme.activate ) {
                        $( '.phoen-theme-data-importer-import-progress' ).show();
                        $( '.phoen-theme-data-importer--no-data-btn' ).replaceWith( '<a href="#" class="phoen-theme-data-importer--import-now button button-primary">' + phoen_data_variable_arguments.import_now + '</a>' );
                    } else {
                        $( '.phoen-theme-data-importer--no-data-btn' ).replaceWith( '<a href="#" class="phoen-theme-data-importer--activate-now button button-primary">'+phoen_data_variable_arguments.activate_theme+'</a>' );
                    }
                }
            };

            Media.on('select', function () {
                var attachment = Media.state().get('selection').first().toJSON();
                var id = attachment.id;
                var file_name = attachment.filename;
                var ext = file_name.split('.').pop();
                if (that.uploading_file == 'xml') {
                    if (ext.toLowerCase() == 'xml') {
                        demo_contents_viewing_theme.xml_id = id;
                        $('.phoen-theme-data-importer--upload-xml').html(file_name);
                        check_upload();
                    }
                }

                if (that.uploading_file == 'json') {
                    if (ext.toLowerCase() == 'txt' || ext.toLowerCase() == 'json') {
                        demo_contents_viewing_theme.json_id = id;
                        $('.phoen-theme-data-importer--upload-json').html(file_name);
                        check_upload();
                    }
                }

            });

            // END Custom upload demo file

        }
    };

    $.fn.phoenImporterData = function() {
        phoenImporterAllData.init();
    };


}( jQuery ));

jQuery( document ).ready( function( $ ){
    $( document ).phoenImporterData();
});
jQuery( document ).ready( function( $ ){
	
    if ($.cookie('phoen_cookie_reload') !=='') {
		
		jQuery("a.phoen-theme-data-importer--preview-theme-btn").trigger("click");
		
		// jQuery(".phoen-theme-data-importer--preview").find('a.phoen-theme-data-importer--import-now').trigger("click");
		setTimeout(function() {   //calls click event after a certain time
	
			jQuery('a.phoen-theme-data-importer--import-now').trigger("click");
			
		   $.cookie("phoen_cookie_reload", '');
		   
		}, 2000);
		
	}
});