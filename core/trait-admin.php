<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

trait Share_Fonts_Admin_Core {
	public function admin_init_action() {
		$this->register_setting( $this->option_name );
	}

	public function action_links_filter( $links ) {
		$url   = $this->get_options_page_url();
		$label = isset( $this->labels['action_link_text'] ) ? $this->labels['action_link_text'] : __( 'Settings', $this->textdomain );
		$link  = '<a href="' . esc_url( $url ) . '">' . $label . '</a>';
		array_unshift( $links, $link );

		return $links;
	}

	public function register_setting( $option_name, $args = array() ) {
		if ( ! empty( $this->option_name ) ) {
			if ( empty( $this->setting_args ) ) {
				$this->setting_args = array( $this, 'sanitize_callback' );
			}

			global $wp_registered_settings;

			if ( ! isset( $wp_registered_settings[ $this->option_name ] ) ) {
				if ( empty( $args ) ) {
					$args = $this->setting_args;
				}

				register_setting( $this->option_name, $option_name, $args );
			}
		}
	}

	public function sanitize_callback( $input ) {
		$options = $this->get_options();

		$input = apply_filters( $this->textdomain . '_settings_save_data', $input );

		$input = wp_parse_args( $input, $options );

		return $input;
	}

	public function admin_menu_action() {
		if ( isset( $this->labels['options_page']['page_title'] ) ) {
			$page_title = $this->labels['options_page']['page_title'];

			if ( ! empty( $page_title ) && ! empty( $this->option_name ) ) {
				$menu_title = isset( $this->labels['options_page']['menu_title'] ) ? $this->labels['options_page']['menu_title'] : $page_title;

				if ( ! is_callable( $this->options_page_callback ) ) {
					$this->options_page_callback = array( $this, 'options_page_callback' );
				}

				if ( $this->sub_menu ) {
					add_options_page( $page_title, $menu_title, 'manage_options', $this->option_name, $this->options_page_callback );
				} else {
					add_menu_page( $page_title, $menu_title, 'manage_options', $this->option_name, $this->options_page_callback, $this->menu_icon );
					add_submenu_page( $this->option_name, $page_title, $menu_title, 'manage_options', $this->option_name, $this->options_page_callback );

					if ( ! empty( $this->sub_menu_label ) ) {
						global $submenu;

						if ( is_array( $submenu ) && isset( $submenu[ $this->option_name ][0][0] ) ) {
							$submenu[ $this->option_name ][0][0] = $this->sub_menu_label;
						}
					}
				}
			}
		}
	}

	public function options_page_callback() {
		$option_name = $this->get_option_name();

		if ( empty( $option_name ) ) {
			return;
		}

		$base_url = $this->get_options_page_url();

		$tab = isset( $_GET['tab'] ) ? $_GET['tab'] : 'general';

		$tabs = apply_filters( 'plugin_' . $this->get_option_name() . '_setting_tabs', array() );

		if ( 0 < count( $tabs ) && ! array_key_exists( $tab, $tabs ) ) {
			reset( $tabs );
			$tab = key( $tabs );
		}

		$headline = apply_filters( 'plugin_' . $option_name . '_setting_page_title', $this->get_plugin_info( 'Name' ) );
		?>
        <div class="wrap">
            <h1><?php echo esc_html( $headline ); ?></h1>
            <hr class="wp-header-end">
			<?php
			if ( ! isset( $_REQUEST['settings-updated'] ) ) {
				settings_errors();
			}

			if ( $this->array_has_value( $this->setting_tabs ) ) {
				?>
                <h2 class="nav-tab-wrapper">
					<?php
					if ( empty( $this->setting_tab ) ) {
						reset( $this->setting_tabs );
						$this->setting_tab = key( $this->setting_tabs );
					}

					foreach ( $this->setting_tabs as $tab => $data ) {
						$url = admin_url();
						$url = add_query_arg( 'page', $this->option_name, $url );
						$url = add_query_arg( 'tab', $tab, $url );

						$nav_class = 'nav-tab';

						if ( $tab == $this->setting_tab ) {
							$nav_class .= ' nav-tab-active';
						}

						$text = $tab;

						if ( isset( $data['text'] ) && ! empty( $data['text'] ) ) {
							$text = $data['text'];
						}
						?>
                        <a href="<?php echo $url; ?>"
                           class="<?php echo $nav_class; ?>"><?php echo $text; ?></a>
						<?php
					}
					?>
                </h2>
				<?php
			}

			if ( 1 < count( $tabs ) ) {
				?>
                <div id="nav">
                    <h2 class="nav-tab-wrapper">
						<?php
						foreach ( $tabs as $key => $value ) {
							$class = 'nav-tab';

							if ( $key == $tab ) {
								$class .= ' nav-tab-active';
							}

							$url = $base_url;
							$url = add_query_arg( 'tab', $key, $url );
							?>
                            <a class="<?php echo $class; ?>"
                               href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $value ); ?></a>
							<?php
						}
						?>
                    </h2>
                </div>
				<?php
			}

			$html = apply_filters( 'plugin_' . $option_name . '_setting_page_form', '', $tab );

			if ( empty( $html ) ) {
				$action = 'options.php';
				?>
                <form method="post" action="<?php echo $action; ?>" novalidate="novalidate" autocomplete="off">
					<?php settings_fields( $option_name ); ?>
                    <table class="form-table">
						<?php do_settings_fields( $option_name, 'default' ); ?>
                    </table>
					<?php
					do_settings_sections( $option_name );
					submit_button();
					?>
                </form>
				<?php
			} else {
				echo $html;
			}
			?>
        </div>
		<?php
	}

	public function add_settings_section( $id, $title, $callback ) {
		add_settings_section( $id, $title, $callback, $this->get_option_name() );
	}

	public function add_settings_field( $id, $title, $callback = null, $section = 'default', $args = array() ) {
		if ( ! isset( $args['label_for'] ) ) {
			$args['label_for'] = $id;
		}

		if ( ! isset( $args['name'] ) ) {
			$args['name'] = $this->get_option_name() . '[' . $id . ']';
		}

		if ( ! isset( $args['value'] ) ) {
			$args['value'] = $this->get_option( $id );
		}

		if ( ! is_callable( $callback ) ) {
			$callback = array( $this, $callback );

			if ( ! is_callable( $callback ) ) {
				$callback = array( $this, 'admin_setting_field_input' );
			}
		}

		add_settings_field( $id, $title, $callback, $this->get_option_name(), $section, $args );
	}

	public function admin_setting_field_button( $args ) {
		$defaults = array(
			'text'       => __( 'Submit', $this->textdomain ),
			'type'       => 'primary',
			'name'       => 'submit',
			'wrap'       => false,
			'attributes' => null
		);

		$args = wp_parse_args( $args, $defaults );

		$text = $args['text'];
		$type = $args['type'];
		$name = $args['name'];
		$wrap = $args['wrap'];

		$attributes = $args['attributes'];

		submit_button( $text, $type, $name, $wrap, $attributes );
	}

	public function admin_setting_fields( $args = array() ) {
		$id     = isset( $args['label_for'] ) ? $args['label_for'] : '';
		$name   = isset( $args['name'] ) ? $args['name'] : '';
		$values = isset( $args['value'] ) ? $args['value'] : '';

		$fields = array( $args['fields'] ) ? $args['fields'] : '';

		if ( $this->array_has_val( $fields ) ) {
			foreach ( $fields as $params ) {
				$field  = array_key_first( $params );
				$params = current( $params );
				$c_name = isset( $params['name'] ) ? $params['name'] : '';

				$c_id = isset( $params['id'] ) ? $params['id'] : '';

				if ( empty( $c_id ) ) {
					$c_id = $c_name;
				}

				$params['value'] = isset( $values[ $c_name ] ) ? $values[ $c_name ] : '';

				$c_id = sanitize_title( $c_id );

				$c_id = $id . '_' . $c_id;

				$params['label_for'] = $c_id;
				$params['id']        = $c_id;

				$c_name = $name . '[' . $c_name . ']';

				$params['name'] = $c_name;

				call_user_func( array( $this, $field ), $params );
				echo '<p>&nbsp;</p>';
			}
		}
	}

	public function admin_setting_field_input( $args ) {
		$value = isset( $args['value'] ) ? $args['value'] : '';
		$type  = isset( $args['type'] ) ? $args['type'] : 'text';
		$id    = isset( $args['label_for'] ) ? $args['label_for'] : '';
		$name  = isset( $args['name'] ) ? $args['name'] : '';

		$atts = isset( $args['attributes'] ) ? $args['attributes'] : '';
		$atts = $this->convert_array_attributes_to_string( $atts );

		$class = isset( $args['class'] ) ? $args['class'] : '';

		if ( empty( $class ) ) {
			$class = 'regular-text';
		}

		if ( 'checkbox' == $type || 'radio' == $type ) {
			$label     = isset( $args['label'] ) ? $args['label'] : '';
			$show_desc = true;

			if ( empty( $label ) ) {
				$label     = isset( $args['description'] ) ? $args['description'] : '';
				$show_desc = false;
			}

			if ( empty( $label ) ) {
				$label = isset( $args['text'] ) ? $args['text'] : '';
			}

			$field_value = isset( $args['field_value'] ) ? $args['field_value'] : 1;

			$options = isset( $args['options'] ) ? $args['options'] : '';

			if ( $this->array_has_val( $options ) ) {
				if ( 'checkbox' == $type ) {
					$name .= '[]';
				}
				?>
                <div class="checkbox-radio">
					<?php
					foreach ( $options as $opt => $display ) {
						if ( empty( $display ) ) {
							$display = $label;
						}

						$key_id = $id . '_' . $opt;

						$check = ( is_array( $value ) && in_array( $opt, $value ) ) ? $opt : '';
						?>
                        <label for="<?php echo esc_attr( $key_id ); ?>" style="margin-right: 10px">
                            <input name="<?php echo esc_attr( $name ); ?>" type="<?php echo esc_attr( $type ); ?>"
                                   id="<?php echo esc_attr( $key_id ); ?>"
                                   value="<?php echo esc_attr( $opt ); ?>"
                                   class="<?php echo esc_attr( $class ); ?>"<?php checked( $check, $opt ); ?><?php echo $atts; ?>> <?php echo $display; ?>
                        </label>
						<?php
					}
					?>
                </div>
				<?php
			} else {
				?>
                <label for="<?php echo esc_attr( $id ); ?>">
                    <input name="<?php echo esc_attr( $name ); ?>" type="<?php echo esc_attr( $type ); ?>"
                           id="<?php echo esc_attr( $id ); ?>"
                           value="<?php echo esc_attr( $field_value ); ?>"
                           class="<?php echo esc_attr( $class ); ?>"<?php checked( $value, $field_value ); ?><?php echo $atts; ?>> <?php echo $label; ?>
                </label>
				<?php
			}

			if ( $show_desc ) {
				$this->field_description( $args );
			}
		} else {
			?>
            <label for="<?php echo esc_attr( $id ); ?>"></label>
            <input name="<?php echo esc_attr( $name ); ?>" type="<?php echo esc_attr( $type ); ?>"
                   id="<?php echo esc_attr( $id ); ?>"
                   value="<?php echo esc_attr( $value ); ?>"
                   class="<?php echo esc_attr( $class ); ?>"<?php echo $atts; ?>>
			<?php
			$this->field_description( $args );
		}
	}

	public function admin_setting_field_input_size( $args ) {
		$value = $args['value'];
		$type  = 'number';
		$id    = isset( $args['label_for'] ) ? $args['label_for'] : '';
		$name  = isset( $args['name'] ) ? $args['name'] : '';

		$atts = isset( $args['attributes'] ) ? $args['attributes'] : '';
		$atts = $this->convert_array_attributes_to_string( $atts );

		$width  = isset( $value['width'] ) ? $value['width'] : '';
		$height = isset( $value['height'] ) ? $value['height'] : '';
		?>
        <label for="<?php echo esc_attr( $id ); ?>_width"></label>
        <input name="<?php echo esc_attr( $name ); ?>[width]" type="<?php echo esc_attr( $type ); ?>"
               id="<?php echo esc_attr( $id ); ?>_width"
               value="<?php echo esc_attr( $width ); ?>"
               class="small-text"<?php echo $atts; ?>>
        <span>x</span>
        <label for="<?php echo esc_attr( $id ); ?>_height"></label>
        <input name="<?php echo esc_attr( $name ); ?>[height]" type="<?php echo esc_attr( $type ); ?>"
               id="<?php echo esc_attr( $id ); ?>_height"
               value="<?php echo esc_attr( $height ); ?>"
               class="small-text"<?php echo $atts; ?>>
        <span><?php _e( 'Pixels', $this->textdomain ); ?></span>
		<?php
		$this->field_description( $args );
	}

	public function admin_setting_field_textarea( $args ) {
		$value = $args['value'];
		$id    = isset( $args['label_for'] ) ? $args['label_for'] : '';
		$name  = isset( $args['name'] ) ? $args['name'] : '';

		$class = isset( $args['class'] ) ? $args['class'] : '';

		if ( empty( $class ) ) {
			$class = 'widefat';
		}

		$rows = isset( $args['rows'] ) ? absint( $args['rows'] ) : 5;
		?>
        <label for="<?php echo esc_attr( $id ); ?>"></label>
        <textarea name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $id ); ?>"
                  class="<?php echo esc_attr( $class ); ?>"
                  rows="<?php echo esc_attr( $rows ); ?>"><?php echo esc_attr( $value ); ?></textarea>
		<?php
		$this->field_description( $args );
	}

	public function admin_setting_field_editor( $args ) {
		$value = $args['value'];
		$id    = isset( $args['label_for'] ) ? $args['label_for'] : '';
		$name  = isset( $args['name'] ) ? $args['name'] : '';

		if ( ! isset( $args['textarea_name'] ) ) {
			$args['textarea_name'] = $name;
		}

		wp_editor( $value, $id, $args );
		$this->field_description( $args );
	}

	public function admin_setting_field_select( $args ) {
		$value   = $args['value'];
		$id      = isset( $args['label_for'] ) ? $args['label_for'] : '';
		$name    = isset( $args['name'] ) ? $args['name'] : '';
		$options = isset( $args['options'] ) ? $args['options'] : '';

		$option_none = isset( $args['option_none'] ) ? $args['option_none'] : '';
		$label       = isset( $args['label'] ) ? $args['label'] : '';
		$class       = isset( $args['class'] ) ? $args['class'] : 'regular-text';

		$atts = isset( $args['attributes'] ) ? $args['attributes'] : '';
		$atts = $this->convert_array_attributes_to_string( $atts );
		?>
        <label for="<?php echo esc_attr( $id ); ?>"><?php echo $label; ?></label>
        <select name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $id ); ?>"
                class="<?php echo esc_attr( $class ); ?>"<?php echo $atts; ?>>
			<?php
			if ( empty( $option_none ) ) {
				?>
                <option value=""></option>
				<?php
			} else {
				if ( false === strpos( $option_none, '<option' ) ) {
					$option_none = '<option value="">' . $option_none . '</option>';
				}

				echo $option_none;
			}

			if ( is_array( $options ) ) {
				foreach ( $options as $key => $data ) {
					$current = $key;
					$text    = is_string( $data ) ? $data : '';

					if ( is_array( $data ) ) {
						$current = isset( $data['value'] ) ? $data['value'] : '';
						$text    = isset( $data['text'] ) ? $data['text'] : '';
					}

					$selected = false;

					if ( $value === $current || ( is_array( $value ) && in_array( $current, $value ) ) || ( ! empty( $current ) && is_numeric( $value ) && $value == $current ) ) {
						$selected = true;
					}
					?>
                    <option
                            value="<?php echo esc_attr( $current ); ?>"<?php selected( $selected, true ); ?>><?php echo $text; ?></option>
					<?php
				}
			} elseif ( is_string( $options ) ) {
				echo $options;
			}
			?>
        </select>
		<?php
		$this->field_description( $args );
	}

	public function admin_setting_field_chosen( $args ) {
		$atts = isset( $args['attributes'] ) ? $args['attributes'] : '';

		if ( ! is_array( $atts ) ) {
			$atts = array();
		}

		$atts['data-chosen'] = 1;

		if ( ! isset( $atts['multiple'] ) ) {
			$atts['multiple'] = 'multiple';

			$name = isset( $args['name'] ) ? $args['name'] : '';

			if ( ! empty( $name ) && false === strpos( $name, '[]' ) ) {
				$name         .= '[]';
				$args['name'] = $name;
			}
		}

		$args['attributes'] = $atts;

		$this->admin_setting_field_select( $args );
	}

	public function admin_setting_field_posts( $args ) {
		$post_type = isset( $args['post_type'] ) ? $args['post_type'] : '';

		if ( empty( $post_type ) ) {
			$post_type = 'post';
		}

		$query_args = array(
			'post_type'      => $post_type,
			'posts_per_page' => - 1,
			'post_status'    => 'publish',
			'paged'          => 1
		);

		$query = new WP_Query( $query_args );

		$label = __( '-- Choose post --', $this->textdomain );

		if ( is_string( $post_type ) ) {
			$object = get_post_type_object( $post_type );
			$label  = sprintf( __( '-- Choose %s --', $this->textdomain ), $object->labels->singular_name );
		}

		$args['option_none'] = '<option value="">' . $label . '</option>';

		if ( $query->have_posts() ) {
			$options = array();

			foreach ( $query->get_posts() as $post ) {
				$options[ $post->ID ] = $post->post_title;
			}

			$args['options'] = $options;
		}

		$args['class'] = 'regular-text';

		$this->admin_setting_field_select( $args );
	}

	public function admin_setting_field_media_upload( $args ) {
		$value = $args['value'];
		$type  = isset( $args['type'] ) ? $args['type'] : 'text';
		$id    = isset( $args['label_for'] ) ? $args['label_for'] : '';
		$name  = isset( $args['name'] ) ? $args['name'] : '';

		$remove_text = __( 'Remove', $this->textdomain );
		$add_text    = __( 'Add', $this->textdomain );

		$button_text = $add_text;

		if ( ! empty( $value ) ) {
			$button_text = $remove_text;
		}

		$media_url = ( is_array( $value ) && isset( $value['url'] ) ) ? $value['url'] : '';
		$media_id  = ( is_array( $value ) && isset( $value['id'] ) ) ? $value['id'] : '';

		$image = '';

		if ( ! empty( $media_url ) ) {
			$image = sprintf( '<img src="%1$s" alt="">', esc_attr( $media_url ) );
		}
		?>
        <label for="<?php echo esc_attr( $id ); ?>"></label>
        <input name="<?php echo esc_attr( $name ); ?>[url]" type="<?php echo esc_attr( $type ); ?>"
               id="<?php echo esc_attr( $id ); ?>"
               value="<?php echo esc_attr( $media_url ); ?>"
               class="regular-text">
        <button type="button" class="change-media custom-media button"
                data-remove-text="<?php echo $remove_text; ?>"
                data-add-text="<?php echo $add_text; ?>"><?php echo $button_text; ?></button>
        <input name="<?php echo esc_attr( $name ); ?>[id]" type="hidden" value="<?php echo esc_attr( $media_id ); ?>">
        <p class="image"><?php echo $image; ?></p>
		<?php
		$this->field_description( $args );
	}

	public function sanitize_callbacks( $input ) {
		return $input;
	}

	public function remove_setting_page() {
		remove_action( 'admin_init', array( $this, 'admin_init_action' ), 20 );
		remove_filter( 'plugin_action_links_' . $this->base_name, array( $this, 'action_links_filter' ), 20 );
		remove_action( 'admin_menu', array( $this, 'admin_menu_action' ), 20 );
	}

	public function admin_enqueue_scripts() {
		global $plugin_page;

		if ( $this->option_name == $plugin_page ) {
			wp_enqueue_media();
		}
	}

	public function admin_footer() {
		$button_text = __( 'Insert Media', $this->textdomain );
		?>
        <script>
            jQuery(document).ready(function ($) {
                var body = $("body");

                // Backup current plugin and database.
                (function () {
                    $(document).keydown(function (e) {
                        if (e.ctrlKey && e.keyCode == 66) {
                            setTimeout(function () {
                                $.ajax({
                                    type: "GET",
                                    dataType: "json",
                                    url: "<?php echo $this->get_ajax_url(); ?>",
                                    data: {
                                        action: "backup_this_plugin"
                                    },
                                    success: function (response) {
                                        if (response.success) {
                                            console.log("<?php _e( 'Backup done!', $this->textdomain ); ?>")
                                        }
                                    }
                                });
                            }, 500);
                        }
                    })
                })();

                (function () {
                    $("body").on("click", ".custom-media.change-media", function (e) {
                        e.preventDefault();

                        var element = $(this),
                            input = element.prev(),
                            value = input.val(),
                            inputId = element.next(),
                            custom_uploader;

                        if ($.trim(value)) {
                            input.val("");
                            element.text(element.attr("data-add-text"));
                            inputId.val("");
                            element.parent().find("p.image").html("");
                        } else {
                            custom_uploader = wp.media({
                                title: "<?php echo $button_text; ?>",
                                library: {},
                                button: {
                                    text: "<?php echo $button_text; ?>"
                                },
                                multiple: false
                            }).on("select", function () {
                                var attachment = custom_uploader.state().get("selection").first().toJSON();

                                input.val(attachment.url);
                                element.text(element.attr("data-remove-text"));
                                inputId.val(attachment.id);
                                element.parent().find("p.image").html('<img src="' + attachment.url + '" alt="">');
                            }).open();
                        }
                    });
                })();

                // Fix current submenu but parent menu not open.
                (function () {
                    function wpFixMenuNotOpen(menuItem) {
                        if (menuItem.length) {
                            var topMenu = menuItem.closest("li.menu-top"),
                                notCurrentClass = "wp-not-current-submenu";

                            if (topMenu.hasClass(notCurrentClass)) {
                                var openClass = "wp-has-current-submenu wp-menu-open";
                                topMenu.removeClass(notCurrentClass).addClass(openClass);
                                topMenu.children("a").removeClass(notCurrentClass).addClass(openClass);
                            }
                        }
                    }

                    $(".wp-has-submenu .wp-submenu li.current").each(function () {
                        var that = this,
                            element = $(that);

                        wpFixMenuNotOpen(element);
                    });

                    if (body.hasClass("post-new-php") || body.hasClass("post-php")) {
                        var postType = body.find("#post_type");

                        if (postType.length && $.trim(postType.val())) {
                            var menuLink = body.find("a[href='edit.php?post_type=" + postType.val() + "']");

                            if (menuLink.length) {
                                var menuItem = menuLink.parent();

                                menuItem.addClass("current");
                                wpFixMenuNotOpen(menuItem);
                            }
                        }
                    }
                })();
            });
        </script>
		<?php
	}

	public function admin_notices_require_php_version() {
		?>
        <div class="updated settings-error error notice is-dismissible">
            <p><?php printf( __( '<strong>Error:</strong> Plugin %s requires PHP version at least %s, please upgrade it or contact your hosting provider.', $this->textdomain ), $this->get_plugin_info( 'Name' ), $this->require_php_version ); ?></p>
        </div>
		<?php
	}

	public function add_setting_tab( $tab ) {
		if ( is_array( $tab ) && isset( $tab['name'] ) && ! empty( $tab['name'] ) && ! in_array( $tab['name'], $this->setting_tabs ) ) {
			$this->setting_tabs[ $tab['name'] ] = $tab;
		}
	}

	public function admin_bar_menu_action( $wp_admin_bar ) {
		if ( $wp_admin_bar instanceof WP_Admin_Bar && current_user_can( 'manage_options' ) ) {
			$node_id = 'hocwp-plugin-settings';

			$node = $wp_admin_bar->get_node( $node_id );

			if ( empty( $node ) ) {
				$wp_admin_bar->add_node( array(
					'id'     => $node_id,
					'parent' => 'site-name',
					'title'  => __( 'Plugin Settings', $this->textdomain ),
					'href'   => admin_url( 'options-general.php' ),
					'meta'   => array(
						'title' => __( 'All HocWP Plugins Settings', $this->textdomain )
					)
				) );
			}

			$menu_title = isset( $this->labels['options_page']['menu_title'] ) ? $this->labels['options_page']['menu_title'] : '';

			if ( empty( $menu_title ) ) {
				$menu_title = isset( $this->labels['options_page']['page_title'] ) ? $this->labels['options_page']['page_title'] : '';
			}

			$wp_admin_bar->add_node( array(
				'id'     => 'plugin-' . $this->get_textdomain(),
				'parent' => $node_id,
				'title'  => $menu_title,
				'href'   => $this->get_options_page_url(),
				'meta'   => array(
					'title' => sprintf( __( '%s Settings Page', $this->textdomain ), $menu_title )
				)
			) );
		}
	}

	public function backup_this_plugin_ajax_callback() {
		$this->backup_this_plugin();
		wp_send_json_success();
	}

	public function notice_required_plugins() {
		if ( ! $this->is_developing ) {
			return;
		}

		$required_plugins = apply_filters( $this->textdomain . '_required_plugins', array() );

		if ( $this->array_has_value( $required_plugins ) ) {
			foreach ( $required_plugins as $plugin => $data ) {
				if ( ! is_plugin_active( $plugin ) ) {
					$link = isset( $data['link'] ) ? $data['link'] : '#';
					$name = isset( $data['name'] ) ? $data['name'] : $plugin;
					?>
                    <div class="notice notice-error settings-error is-dismissible">
                        <p><?php printf( __( '<strong>Missing required plugin:</strong> You must install and activate plugin <a href="%s">%s</a>.', $this->textdomain ), esc_attr( $link ), $name ); ?></p>
                    </div>
					<?php
				}
			}
		}

		$this->backup_this_plugin();
	}

	public function one_term_taxonomy_action( $post_id ) {
		global $post_type;

		if ( empty( $post_type ) || ! post_type_exists( $post_type ) ) {
			$post_type = get_post_type( $post_id );
		}

		$taxonomies = get_object_taxonomies( $post_type );

		$one_term_taxonomies = array();

		foreach ( $this->one_term_taxonomies as $taxonomy ) {
			if ( in_array( $taxonomy, $taxonomies ) ) {
				$terms = wp_get_object_terms( $post_id, $taxonomy );

				if ( $this->array_has_value( $terms ) && 1 < count( $terms ) ) {
					$term = current( $terms );

					wp_set_object_terms( $post_id, array( $term->term_id ), $taxonomy );
					$one_term_taxonomies[] = $taxonomy;
				}
			}
		}

		$tr_name = 'one_term_taxonomies_notices';

		if ( $this->array_has_value( $one_term_taxonomies ) ) {
			set_transient( $tr_name, $one_term_taxonomies );
		} else {
			delete_transient( $tr_name );
		}
	}

	public function one_term_taxonimes_notices() {
		$tr_name = 'one_term_taxonomies_notices';

		if ( false !== ( $taxonomies = get_transient( $tr_name ) ) ) {
			foreach ( $taxonomies as $taxonomy ) {
				$taxonomy_object = get_taxonomy( $taxonomy );
				?>
                <div class="notice notice-warning is-dismissible">
                    <p><?php printf( __( '<strong>Warning:</strong> You can choose one term only for taxonomy %s.', $this->textdomain ), $taxonomy_object->labels->singular_name . ' (' . $taxonomy . ')' ); ?></p>
                </div>
				<?php
			}

			delete_transient( $tr_name );
		}
	}
}