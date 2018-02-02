<?php
/*
Plugin Name: WordPress Preview Everywhere
Plugin URI: http://www.danielealessandra.com/
Version: 3.0.2
Description: Preview your content in whole site, not only as a Single Page.
Author: Daniele Alessandra
Author URI: http://www.danielealessandra.com
*/
/*
THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.
*/
class WordPress_Preview_Everywhere {
    public function __construct() {
        add_action( 'admin_print_scripts-post.php', array( $this, 'enqueue_scripts' ) );
        add_action( 'admin_print_scripts-edit.php', array( $this, 'enqueue_scripts' ) );
        add_action( 'admin_print_scripts-post-new.php', array( $this, 'enqueue_scripts' ) );
        add_filter( 'parse_query', array( $this, 'filter_parse_query' ) );
        add_action( 'save_post', array( $this, 'save_post_status' ) );
        add_action( 'init', array( $this, 'set_user_post_status' ), 0 );
        add_filter( 'display_post_states', array( $this, 'display_post_state' ) );
    }
    public function enqueue_scripts() {
        if ( 'post.php' == $GLOBALS['pagenow'] ) {
            global $post;
            $state_list = array( 'draft', 'only_for_', 'pending' );
            if ( in_array( $post->post_status, $state_list ) || preg_match( '/only_for_(\d)+/', $post->post_status ) ) {
                wp_register_script( 'previeweverywhereadminscript', plugins_url( 'js/admin-script.js', __FILE__ ), array( 'jquery' ) );
                wp_enqueue_script( 'previeweverywhereadminscript' );
                wp_register_style( 'previeweverywhereadminstyle', plugins_url( 'css/admin-style.css', __FILE__ ) );
                wp_enqueue_style( 'previeweverywhereadminstyle' );
            }
        }
    }
    public function filter_parse_query( $wp_query ) {
        if ( 'index.php' == $GLOBALS['pagenow'] ) {
            if ( current_user_can( 'edit_posts' ) ) {
                global $current_user;
                $post_status = $wp_query->get( 'post_status' );
                $my_post_status = 'only_for_' . $current_user->ID;
                if ( is_array( $post_status ) ) {
                    if (!in_array( $my_post_status, $post_status ) ) {
                        $post_status[] = $my_post_status;
                    }
                } else if ( 'true' != @$_GET['preview'] ) {
                    $post_status = array();
                    $post_status[] = 'publish';
                    $post_status[] = $my_post_status;
                    if ( current_user_can( 'administrator' ) ) {
                        $post_status[] = 'private';
                    }
                }
                if ( is_array( $post_status ) ) {
                    $wp_query->set( 'post_status', $post_status );
                }
            }
        }
        return $wp_query;
    }
    function save_post_status( $post_id ) {
        if ( 'dopreview' == @$_POST['wp-preview'] ) {
            $parent_post_id = wp_is_post_revision( $post_id );
            if ( (int)$parent_post_id > 0 ) {
                print_r( $parent_post_id );
                $post_id = $parent_post_id;
            }
            global $current_user;
            $current_user_preview_status = 'only_for_' . $current_user->ID;
            $current_post_status = get_post_status( $post_id );
            $state_list = array( 'draft', 'only_for_', 'pending' );
            if ( in_array( $current_post_status, $state_list ) ) {
                $post_args = array();
                $post_args['ID'] = $post_id;
                $post_args['post_status'] = $current_user_preview_status;
                remove_action( 'save_post', array( $this, 'save_post_status' ) );
                wp_update_post( $post_args );
                add_action( 'save_post', array( $this, 'save_post_status' ) );
            }
        }
    }
    public function set_user_post_status() {
        if ( current_user_can( 'edit_posts' ) ) {
            global $wpdb;
            global $current_user;
            $current_user_status = 'only_for_' . $current_user->ID;
            $preview_status_list = array();
            $used_status_list = $wpdb->get_results( "SELECT DISTINCT post_status FROM $wpdb->posts WHERE post_status LIKE 'only_for_%'");
            foreach( $used_status_list as $lbl => $used_status ) {
                $preview_status_list[] = $used_status->post_status;
            }
            if ( !in_array( $current_user_status, $preview_status_list ) ) {
                $preview_status_list[] = $current_user_status;
            }
            foreach( $preview_status_list as $status ) {
                if ( preg_match( '/only_for_(\d)+/', $status ) ) {
                    $user_info = get_userdata( filter_var($status, FILTER_SANITIZE_NUMBER_INT) );
                    $args = array(
                        'label'                     => 'preview',
                        'label_count'               => _n_noop( "❥ $user_info->user_nicename (%s)",  "❥ $user_info->user_nicename (%s)", 'text_domain' ),
                        'public'                    => true,
                        'show_in_admin_all_list'    => true,
                        'show_in_admin_status_list' => true,
                        'exclude_from_search'       => false,
                    );
                    register_post_status( $status, $args );
                }
            }
        }
    }
    public function display_post_state($states) {
        global $post;
        $post_status = get_post_status( $post->ID );
        if ( preg_match( '/only_for_(\d)+/', $post_status ) ) {
            $user_info = get_userdata( filter_var($post_status, FILTER_SANITIZE_NUMBER_INT) );
            $states[] = '<span class="wppe_state ' . $post_status . '">❥ ' . $user_info->user_nicename . '</span>';
        }
        return $states;
    }
}
$wordpress_preview_everywhere = new WordPress_Preview_Everywhere();
