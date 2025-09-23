<?php
/**
 * Plugin Name: Contribute to WordPress
 * Plugin URI: https://github.com/wordpress/wordpress-develop
 * Description: A plugin to help users set up their development environment for contributing to WordPress core.
 * Version: 1.0.0
 * Author: WordPress Community
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: contribute-to-wordpress
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
define( 'CTW_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CTW_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'CTW_VERSION', '1.0.0' );

class ContributeToWordPress {

    private $sections = array();

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action( 'wp_ajax_ctw_check_requirements', array( $this, 'ajax_check_requirements' ) );
        add_action( 'wp_ajax_ctw_verify_username', array( $this, 'ajax_verify_username' ) );
        add_action( 'wp_ajax_ctw_toggle_debug', array( $this, 'ajax_toggle_debug' ) );
        add_action( 'wp_ajax_ctw_set_platform', array( $this, 'ajax_set_platform' ) );
        add_action( 'wp_ajax_ctw_get_stages', array( $this, 'ajax_get_stages' ) );

        $this->init_sections();
    }

    private function init_sections() {
        $this->sections = array(
            'git' => array(
                'name' => 'Git',
                'description' => 'Version control system for tracking changes',
                'check_function' => 'check_git',
                'instructions' => $this->get_git_instructions()
            ),
            'gutenberg' => array(
                'name' => 'Gutenberg Plugin',
                'description' => 'Latest Gutenberg features for block development',
                'check_function' => 'check_gutenberg_plugin',
                'instructions' => $this->get_gutenberg_instructions()
            ),
            'repo' => array(
                'name' => 'Git Repository',
                'description' => 'Working in a Git repository',
                'check_function' => 'check_git_repository',
                'instructions' => $this->get_repo_instructions()
            ),
            'wp_repo' => array(
                'name' => 'WordPress Repository',
                'description' => 'WordPress develop repository setup',
                'check_function' => 'check_wordpress_repository',
                'instructions' => $this->get_wp_repo_instructions()
            ),
            'node' => array(
                'name' => 'Node.js',
                'description' => 'JavaScript runtime for building modern WordPress features',
                'check_function' => 'check_nodejs',
                'instructions' => $this->get_nodejs_instructions()
            ),
            'npm' => array(
                'name' => 'npm',
                'description' => 'Package manager for Node.js dependencies',
                'check_function' => 'check_npm',
                'instructions' => $this->get_npm_instructions()
            )
        );
    }

    public function add_admin_menu() {
        add_management_page(
            'Contribute to WordPress',
            'Contribute to WordPress',
            'manage_options',
            'contribute-to-wordpress',
            array( $this, 'admin_page' )
        );
    }

    public function enqueue_scripts( $hook ) {
        if ( $hook !== 'tools_page_contribute-to-wordpress' ) {
            return;
        }

        wp_enqueue_script( 'ctw-admin', CTW_PLUGIN_URL . 'assets/admin.js', array( 'jquery' ), CTW_VERSION, true );
        wp_enqueue_style( 'ctw-admin', CTW_PLUGIN_URL . 'assets/admin.css', array(), CTW_VERSION );

        wp_localize_script( 'ctw-admin', 'ctw_ajax', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'ctw_nonce' )
        ) );
    }

    public function admin_page() {
        $wporg_username = get_option( 'ctw_wporg_username', '' );
        ?>
        <div class="wrap" id="ctw-admin">
            <h1>WordPress Contribution Readiness</h1>

            <!-- Contribution Stages -->
            <div class="contribution-stages">
                <h2>Your Contribution Journey</h2>
                <p>Check your current stage in WordPress contribution readiness:</p>

                <div id="contribution-stages-container">
                    <?php $this->render_contribution_stages(); ?>
                </div>
            </div>

            <!-- Development Environment Status -->
            <div class="checklist-section">
                <h2>💻 Development Environment</h2>
                <p style="display: flex; align-items: center; gap: 10px; margin-bottom: 15px;">
                    <strong>Platform:</strong>
                    <span id="current-platform"><?php echo esc_html( $this->get_platform() ); ?></span>
                    <a href="#" onclick="showPlatformOverride(); return false;" style="font-size: 12px; text-decoration: none; color: #646970;" id="platform-wrong-link">(Wrong?)</a>
                    <select id="platform-override" onchange="overridePlatform(this.value)" style="font-size: 12px; padding: 2px 5px; display: none;">
                        <option value="">Override...</option>
                        <option value="Windows">Windows</option>
                        <option value="macOS">macOS</option>
                        <option value="Linux">Linux</option>
                        <option value="reset">Reset to Real</option>
                    </select>
                </p>

                <div id="environment-checks">
                    <?php $this->render_environment_checks(); ?>
                </div>
            </div>

            <!-- Account Setup -->
            <div class="checklist-section">
                <h2>👤 Account Setup</h2>
                <div class="checklist-item">
                    <div class="checkbox-wrapper">
                        <span class="dashicons <?php echo $wporg_username ? 'dashicons-yes-alt' : 'dashicons-dismiss'; ?>"></span>
                        <strong>WordPress.org Account</strong>
                        <span class="status-badge <?php echo $wporg_username ? 'success' : 'error'; ?>">
                            <?php echo $wporg_username ? '✓ Set: ' . esc_html( $wporg_username ) : '✗ Not Set'; ?>
                        </span>
                    </div>

                    <?php if ( ! $wporg_username ) : ?>
                        <div class="account-form">
                            <table class="form-table">
                                <tr>
                                    <th scope="row">WordPress.org Username</th>
                                    <td>
                                        <input type="text" id="wporg-username" class="regular-text" placeholder="Enter your username">
                                        <button type="button" id="verify-username" class="button button-primary">Verify & Save Username</button>
                                        <div id="username-status"></div>
                                        <p class="description">
                                            Don't have an account? <a href="https://login.wordpress.org/register" target="_blank">Register here</a><br>
                                            <small>We'll verify that your username exists on WordPress.org</small>
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Learning Resources -->
            <?php $this->render_learning_resources(); ?>
        </div>
        <?php
    }

    private function get_stages_data() {
        return array(
            'web' => array(
                'title' => 'Stage 1: GitHub Web Interface Contributions',
                'description' => 'Edit files directly on GitHub without local development setup',
                'icon' => '🌐',
                'requirements' => array()
            ),
            'php' => array(
                'title' => 'Stage 2: PHP Core Development',
                'description' => 'Contribute to WordPress core PHP code, themes, and backend functionality',
                'icon' => '⚙️',
                'requirements' => array( 'git', 'repo', 'wp_repo' )
            ),
            'blocks' => array(
                'title' => 'Stage 3: Block Development',
                'description' => 'Contribute to Gutenberg blocks and modern WordPress JavaScript development',
                'icon' => '🧱',
                'requirements' => array( 'git', 'repo', 'wp_repo', 'node', 'npm' )
            )
        );
    }

    private function render_contribution_stages() {
        $stages = $this->get_stages_data();

        foreach ( $stages as $stage_key => $stage ) {
            $this->render_single_stage( $stage_key, $stage );
        }
    }

    private function render_single_stage( $stage_key, $stage ) {
        $missing_requirements = array();
        $stage_ready = true;

        foreach ( $stage['requirements'] as $req ) {
            if ( ! $this->check_requirement( $req ) ) {
                $missing_requirements[] = $this->sections[$req]['name'];
                $stage_ready = false;
            }
        }

        $status_class = $stage_ready ? 'available' : 'not_ready';
        $status_icon = $stage_ready ? '✅' : '⭕';
        $border_color = $stage_ready ? '#2ea44f' : '#d1d5da';
        $bg_color = $stage_ready ? '#f0fff4' : '#fafbfc';
        $text_color = $stage_ready ? '#2ea44f' : '#586069';

        ?>
        <div class="stage-item <?php echo esc_attr( $status_class ); ?>" data-stage="<?php echo esc_attr( $stage_key ); ?>"
             style="border: 2px solid <?php echo esc_attr( $border_color ); ?>; border-radius: 6px; padding: 20px; margin-bottom: 15px; background: <?php echo esc_attr( $bg_color ); ?>;">
            <div style="display: flex; align-items: center; gap: 15px;">
                <div style="font-size: 24px;"><?php echo esc_html( $stage['icon'] ); ?></div>
                <div style="flex: 1;">
                    <h3 style="margin: 0; font-size: 18px; color: <?php echo esc_attr( $text_color ); ?>;">
                        <?php echo esc_html( $status_icon . ' ' . $stage['title'] ); ?>
                    </h3>
                    <p style="margin: 5px 0; color: #586069;"><?php echo esc_html( $stage['description'] ); ?></p>

                    <?php if ( ! $stage_ready && ! empty( $missing_requirements ) ) : ?>
                        <p style="margin: 10px 0 0 0; color: #d73a49; font-size: 14px;">
                            <strong>Missing:</strong> <?php echo esc_html( implode( ', ', $missing_requirements ) ); ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }

    private function render_environment_checks() {
        foreach ( $this->sections as $key => $section ) {
            $is_available = $this->check_requirement( $key );
            $status_class = $is_available ? 'complete' : 'incomplete';
            $icon_class = $is_available ? 'dashicons-yes-alt' : 'dashicons-dismiss';
            $status_text = $is_available ? '✓ Available' : '✗ Not available';
            $status_badge_class = $is_available ? 'success' : 'error';

            ?>
            <div class="checklist-item <?php echo esc_attr( $status_class ); ?>" data-section="<?php echo esc_attr( $key ); ?>">
                <div class="checkbox-wrapper">
                    <span class="dashicons <?php echo esc_attr( $icon_class ); ?>"></span>
                    <strong><?php echo esc_html( $section['name'] ); ?></strong>
                    <span class="status-badge <?php echo esc_attr( $status_badge_class ); ?>"
                          onclick="toggleDebugMode('<?php echo esc_attr( $key ); ?>')"
                          style="cursor: pointer;"
                          title="Click to toggle debug mode">
                        <?php echo esc_html( $status_text ); ?>
                    </span>
                </div>

                <?php if ( ! $is_available ) : ?>
                    <div class="instructions" id="instructions-<?php echo esc_attr( $key ); ?>">
                        <?php echo wp_kses_post( $section['instructions'] ); ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php
        }
    }

    private function render_learning_resources() {
        ?>
        <div class="checklist-section">
            <h2>📚 Learning Resources</h2>
            <p>Familiarize yourself with these important WordPress.org resources:</p>

            <div class="resources-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                <div class="resource-group">
                    <h3>Essential Reading</h3>
                    <ul>
                        <li><a href="https://make.wordpress.org/core/handbook/" target="_blank">Core Contributor Handbook</a></li>
                        <li><a href="https://make.wordpress.org/core/handbook/contribute/" target="_blank">How to Contribute</a></li>
                        <li><a href="https://make.wordpress.org/core/handbook/contribute/git/" target="_blank">Git Workflow</a></li>
                        <li><a href="https://make.wordpress.org/core/handbook/testing/" target="_blank">Testing Guide</a></li>
                    </ul>
                </div>

                <div class="resource-group">
                    <h3>Development Tools</h3>
                    <ul>
                        <li><a href="https://core.trac.wordpress.org/" target="_blank">WordPress Trac</a> - Bug tracking</li>
                        <li><a href="https://github.com/WordPress/wordpress-develop" target="_blank">GitHub Repository</a></li>
                        <li><a href="https://core.trac.wordpress.org/report/40" target="_blank">Good First Bugs</a></li>
                        <li><a href="https://build.trac.wordpress.org/" target="_blank">Build/Test Results</a></li>
                    </ul>
                </div>

                <div class="resource-group">
                    <h3>Community</h3>
                    <ul>
                        <li><a href="https://wordpress.slack.com/" target="_blank">WordPress Slack</a> - #core channel</li>
                        <li><a href="https://make.wordpress.org/core/" target="_blank">Make WordPress Core</a></li>
                        <li><a href="https://make.wordpress.org/core/reports/" target="_blank">Core Reports</a></li>
                        <li><a href="https://make.wordpress.org/" target="_blank">All Make Sites</a></li>
                    </ul>
                </div>
            </div>
        </div>
        <?php
    }

    // Environment detection methods
    private function detect_platform() {
        if ( PHP_OS_FAMILY === 'Windows' ) {
            return 'Windows';
        } elseif ( PHP_OS_FAMILY === 'Darwin' ) {
            return 'macOS';
        } else {
            return 'Linux';
        }
    }

    private function get_platform() {
        $override = get_option( 'ctw_platform_override', '' );
        return $override ? $override : $this->detect_platform();
    }

    private function check_requirement( $key ) {
        if ( ! isset( $this->sections[$key] ) ) {
            return false;
        }

        // Debug mode is now handled client-side only

        $method = $this->sections[$key]['check_function'];
        return $this->$method();
    }

    private function check_git() {
        $output = shell_exec( 'git --version 2>&1' );
        return $output && strpos( $output, 'git version' ) !== false;
    }

    private function check_nodejs() {
        $output = shell_exec( 'node --version 2>&1' );
        return $output && strpos( $output, 'v' ) === 0;
    }

    private function check_npm() {
        $output = shell_exec( 'npm --version 2>&1' );
        return $output && is_numeric( trim( $output )[0] );
    }

    private function check_gutenberg_plugin() {
        return is_plugin_active( 'gutenberg/gutenberg.php' );
    }

    private function check_git_repository() {
        return is_dir( ABSPATH . '.git' ) || is_file( ABSPATH . '.git' );
    }

    private function check_wordpress_repository() {
        if ( ! $this->check_git_repository() ) {
            return false;
        }

        $output = shell_exec( 'cd ' . ABSPATH . ' && git remote get-url origin 2>&1' );
        return $output && ( strpos( $output, 'wordpress-develop' ) !== false || strpos( $output, 'WordPress/wordpress-develop' ) !== false );
    }

    // Instruction methods
    private function get_git_instructions() {
        $platform = $this->get_platform();

        switch ( $platform ) {
            case 'Windows':
                return '<p><strong>Install Git on Windows:</strong></p>
                        <ol>
                            <li>Download Git from <a href="https://git-scm.com/download/win" target="_blank">git-scm.com</a></li>
                            <li>Run the installer and follow the setup wizard</li>
                            <li>Open Command Prompt or PowerShell and verify: <code>git --version</code></li>
                        </ol>';
            case 'macOS':
                return '<p><strong>Install Git on macOS:</strong></p>
                        <ol>
                            <li>Install via Homebrew: <code>brew install git</code></li>
                            <li>Or download from <a href="https://git-scm.com/download/mac" target="_blank">git-scm.com</a></li>
                            <li>Verify installation: <code>git --version</code></li>
                        </ol>';
            default:
                return '<p><strong>Install Git on Linux:</strong></p>
                        <ol>
                            <li>Ubuntu/Debian: <code>sudo apt-get install git</code></li>
                            <li>CentOS/RHEL: <code>sudo yum install git</code></li>
                            <li>Verify installation: <code>git --version</code></li>
                        </ol>';
        }
    }

    private function get_nodejs_instructions() {
        return '<p><strong>Install Node.js:</strong></p>
                <ol>
                    <li>Download from <a href="https://nodejs.org/" target="_blank">nodejs.org</a> (LTS version recommended)</li>
                    <li>Run the installer for your platform</li>
                    <li>Verify installation: <code>node --version</code></li>
                </ol>';
    }

    private function get_npm_instructions() {
        return '<p><strong>npm comes with Node.js:</strong></p>
                <ol>
                    <li>Install Node.js first (see above)</li>
                    <li>npm will be included automatically</li>
                    <li>Verify installation: <code>npm --version</code></li>
                </ol>';
    }

    private function get_gutenberg_instructions() {
        return '<p><strong>Install Gutenberg Plugin:</strong></p>
                <ol>
                    <li>Go to your WordPress admin → Plugins → Add New</li>
                    <li>Search for "Gutenberg"</li>
                    <li>Install and activate the plugin</li>
                    <li>Or download from <a href="https://wordpress.org/plugins/gutenberg/" target="_blank">WordPress.org</a></li>
                </ol>';
    }

    private function get_repo_instructions() {
        return '<p><strong>Initialize Git Repository:</strong></p>
                <ol>
                    <li>Navigate to your WordPress directory</li>
                    <li>Run: <code>git init</code></li>
                    <li>Or clone WordPress develop: <code>git clone https://github.com/WordPress/wordpress-develop.git</code></li>
                </ol>';
    }

    private function get_wp_repo_instructions() {
        return '<p><strong>Setup WordPress Development Repository:</strong></p>
                <ol>
                    <li>Clone the repository: <code>git clone https://github.com/WordPress/wordpress-develop.git</code></li>
                    <li>Navigate to directory: <code>cd wordpress-develop</code></li>
                    <li>Install dependencies: <code>npm install</code></li>
                    <li>Set up local environment: <code>npm run build:dev</code></li>
                </ol>
                <p><a href="https://make.wordpress.org/core/handbook/contribute/git/" target="_blank" class="button">WordPress Git Workflow Guide</a></p>';
    }

    // AJAX handlers
    public function ajax_check_requirements() {
        check_ajax_referer( 'ctw_nonce', 'nonce' );

        $results = array();
        foreach ( $this->sections as $key => $section ) {
            $results[$key] = $this->check_requirement( $key );
        }

        wp_send_json_success( $results );
    }

    public function ajax_verify_username() {
        check_ajax_referer( 'ctw_nonce', 'nonce' );

        $username = sanitize_text_field( $_POST['username'] );

        if ( empty( $username ) ) {
            wp_send_json_error( 'Username is required' );
        }

        // Verify username exists on WordPress.org
        $response = wp_remote_get( 'https://profiles.wordpress.org/' . $username . '/' );

        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
            wp_send_json_error( 'Username not found on WordPress.org' );
        }

        update_option( 'ctw_wporg_username', $username );
        wp_send_json_success( 'Username verified and saved!' );
    }

    public function ajax_toggle_debug() {
        check_ajax_referer( 'ctw_nonce', 'nonce' );

        $section = sanitize_text_field( $_POST['section'] );

        if ( ! isset( $this->sections[$section] ) ) {
            wp_send_json_error( 'Invalid section' );
        }

        // Get the real current state
        $real_state = $this->check_requirement( $section );

        // Return the opposite state for client-side toggle
        wp_send_json_success( array(
            'available' => ! $real_state,
            'real_state' => $real_state,
            'section_data' => $this->sections[$section],
            'debug_mode' => true
        ) );
    }

    private function get_real_requirement_state( $key ) {
        if ( ! isset( $this->sections[$key] ) ) {
            return false;
        }

        $method = $this->sections[$key]['check_function'];
        return $this->$method();
    }

    public function ajax_set_platform() {
        check_ajax_referer( 'ctw_nonce', 'nonce' );

        $platform = sanitize_text_field( $_POST['platform'] );

        if ( $platform === 'reset' ) {
            delete_option( 'ctw_platform_override' );
            wp_send_json_success( array( 'platform' => $this->detect_platform() ) );
        } else {
            $valid_platforms = array( 'Windows', 'macOS', 'Linux' );
            if ( in_array( $platform, $valid_platforms ) ) {
                update_option( 'ctw_platform_override', $platform );
                wp_send_json_success( array( 'platform' => $platform ) );
            } else {
                wp_send_json_error( 'Invalid platform' );
            }
        }
    }

    public function ajax_get_stages() {
        check_ajax_referer( 'ctw_nonce', 'nonce' );

        // Get debug overrides from client
        $debug_overrides = array();
        if ( isset( $_POST['debug_overrides'] ) ) {
            $debug_overrides = json_decode( stripslashes( $_POST['debug_overrides'] ), true );
            if ( ! is_array( $debug_overrides ) ) {
                $debug_overrides = array();
            }
        }

        $stages = $this->get_stages_data();
        $stages_html = '';

        foreach ( $stages as $stage_key => $stage ) {
            ob_start();
            $this->render_single_stage_with_debug( $stage_key, $stage, $debug_overrides );
            $stages_html .= ob_get_clean();
        }

        wp_send_json_success( array( 'html' => $stages_html ) );
    }

    private function render_single_stage_with_debug( $stage_key, $stage, $debug_overrides = array() ) {
        $missing_requirements = array();
        $stage_ready = true;

        foreach ( $stage['requirements'] as $req ) {
            // Check debug override first, then real state
            if ( isset( $debug_overrides[$req] ) ) {
                $requirement_met = $debug_overrides[$req];
            } else {
                $requirement_met = $this->check_requirement( $req );
            }

            if ( ! $requirement_met ) {
                $missing_requirements[] = $this->sections[$req]['name'];
                $stage_ready = false;
            }
        }

        $status_class = $stage_ready ? 'available' : 'not_ready';
        $status_icon = $stage_ready ? '✅' : '⭕';
        $border_color = $stage_ready ? '#2ea44f' : '#d1d5da';
        $bg_color = $stage_ready ? '#f0fff4' : '#fafbfc';
        $text_color = $stage_ready ? '#2ea44f' : '#586069';

        ?>
        <div class="stage-item <?php echo esc_attr( $status_class ); ?>" data-stage="<?php echo esc_attr( $stage_key ); ?>"
             style="border: 2px solid <?php echo esc_attr( $border_color ); ?>; border-radius: 6px; padding: 20px; margin-bottom: 15px; background: <?php echo esc_attr( $bg_color ); ?>;">
            <div style="display: flex; align-items: center; gap: 15px;">
                <div style="font-size: 24px;"><?php echo esc_html( $stage['icon'] ); ?></div>
                <div style="flex: 1;">
                    <h3 style="margin: 0; font-size: 18px; color: <?php echo esc_attr( $text_color ); ?>;">
                        <?php echo esc_html( $status_icon . ' ' . $stage['title'] ); ?>
                    </h3>
                    <p style="margin: 5px 0; color: #586069;"><?php echo esc_html( $stage['description'] ); ?></p>

                    <?php if ( ! $stage_ready && ! empty( $missing_requirements ) ) : ?>
                        <p style="margin: 10px 0 0 0; color: #d73a49; font-size: 14px;">
                            <strong>Missing:</strong> <?php echo esc_html( implode( ', ', $missing_requirements ) ); ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }
}

// Initialize the plugin
new ContributeToWordPress();