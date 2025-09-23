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
                'check_function' => array( $this, 'check_git' ),
                'instructions' => $this->get_git_instructions(),
                'needed_for' => 'Required for all contribution stages to manage code changes and collaborate with the WordPress team.'
            ),
            'wp_repo' => array(
                'name' => 'WordPress Core GitHub Repository',
                'description' => 'WordPress develop repository setup with /src directory',
                'check_function' => array( $this, 'check_wordpress_repository' ),
                'instructions' => $this->get_wp_repo_instructions(),
                'needed_for' => 'Required for PHP and Block development to work with the official WordPress codebase.'
            ),
            'node' => array(
                'name' => 'Node.js',
                'description' => 'JavaScript runtime for building modern WordPress features',
                'check_function' => array( $this, 'check_nodejs' ),
                'instructions' => $this->get_nodejs_instructions(),
                'needed_for' => 'Required for Block development to build and test Gutenberg blocks and modern JavaScript features.'
            ),
            'npm' => array(
                'name' => 'npm',
                'description' => 'Package manager for Node.js dependencies',
                'check_function' => array( $this, 'check_npm' ),
                'instructions' => $this->get_npm_instructions(),
                'needed_for' => 'Required for Block development to install and manage JavaScript packages and build tools.'
            ),
            'gutenberg' => array(
                'name' => 'Gutenberg Plugin',
                'description' => 'Latest Gutenberg features for block development',
                'check_function' => array( $this, 'check_gutenberg_plugin' ),
                'instructions' => $this->get_gutenberg_instructions(),
                'needed_for' => 'Block development happens in the Gutenberg plugin repository.'
            ),
            'wporg_account' => array(
                'name' => 'WordPress.org Account',
                'description' => 'Your WordPress.org community account',
                'check_function' => array( $this, 'check_wporg_account' ),
                'instructions' => $this->get_wporg_account_instructions(),
                'needed_for' => 'Required for contributing back to submit patches, create tickets, and participate in the WordPress community.'
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

        // Enqueue thickbox for plugin installer popup
        add_thickbox();

        wp_localize_script( 'ctw-admin', 'ctw_ajax', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'ctw_nonce' )
        ) );
    }

    public function admin_page() {
        $wporg_username = get_user_meta( get_current_user_id(), 'ctw_wporg_username', true );
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
                        <option value="reset">Reset to Auto-detected</option>
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
                        <?php if ( $wporg_username ) : ?>
                            <a href="#" onclick="editUsername(); return false;" style="margin-left: 10px; font-size: 12px; text-decoration: none; color: #646970;" id="edit-username-link">(Change)</a>
                        <?php endif; ?>
                    </div>

                    <div class="account-form" id="account-form" style="<?php echo $wporg_username ? 'display: none;' : ''; ?>">
                        <table class="form-table">
                            <tr>
                                <th scope="row">WordPress.org Username</th>
                                <td>
                                    <input type="text" id="wporg-username" class="regular-text" placeholder="Enter your username" value="<?php echo esc_attr( $wporg_username ); ?>">
                                    <button type="button" id="verify-username" class="button button-primary">
                                        <?php echo $wporg_username ? 'Update Username' : 'Verify & Save Username'; ?>
                                    </button>
                                    <?php if ( $wporg_username ) : ?>
                                        <button type="button" id="cancel-edit" class="button" style="margin-left: 5px;">Cancel</button>
                                    <?php endif; ?>
                                    <div id="username-status"></div>
                                    <p class="description">
                                        Don't have an account? <a href="https://login.wordpress.org/register" target="_blank">Register here</a><br>
                                        <small>We'll verify that your username exists on WordPress.org</small>
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </div>
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
                'requirements' => array( 'git', 'wp_repo' )
            ),
            'blocks' => array(
                'title' => 'Stage 3: Block Development',
                'description' => 'Contribute to Gutenberg blocks and modern WordPress JavaScript development',
                'icon' => '🧱',
                'requirements' => array( 'git', 'wp_repo', 'node', 'npm', 'gutenberg' )
            ),
            'contribute' => array(
                'title' => 'Stage 4: Contribute Back',
                'description' => 'Submit patches, create tickets, and participate in the WordPress community',
                'icon' => '🚀',
                'requirements' => array( 'wporg_account' )
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
            $check_result = $this->check_requirement( $key );
            $is_available = (bool) $check_result;
            $status_class = $is_available ? 'complete' : 'incomplete';
            $icon_class = $is_available ? 'dashicons-yes-alt' : 'dashicons-dismiss';

            // Show version info if available
            if ( $is_available && is_string( $check_result ) ) {
                $status_text = "✓ " . $check_result . " detected";
            } else {
                $status_text = $is_available ? '✓ Available' : '✗ Not available';
            }

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
                        <div style="background: #f8f9fa; padding: 10px; border-radius: 4px; margin-bottom: 15px; border-left: 3px solid #007cba;">
                            <p style="margin: 0; font-size: 14px; color: #32373c;">
                                <strong>Why you need this:</strong> <?php echo esc_html( $section['needed_for'] ); ?>
                            </p>
                        </div>

                        <?php if ( $key === 'gutenberg' ) : ?>
                            <?php if ( current_user_can( 'install_plugins' ) ) : ?>
                                <p>
                                    <a href="<?php echo esc_url( admin_url( 'plugin-install.php?tab=plugin-information&plugin=gutenberg&TB_iframe=true&width=600&height=550' ) ); ?>"
                                       class="thickbox button button-primary">Install Gutenberg Plugin</a>
                                </p>
                                <p class="description">This will open the plugin installer. After installation, make sure to activate the plugin.</p>
                            <?php else : ?>
                                <?php echo wp_kses_post( $section['instructions'] ); ?>
                            <?php endif; ?>
                        <?php else : ?>
                            <?php echo wp_kses_post( $section['instructions'] ); ?>
                        <?php endif; ?>
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
        // Use PHP_OS for initial detection
        $os = strtolower( PHP_OS );

        if ( strpos( $os, 'win' ) !== false || PHP_OS_FAMILY === 'Windows' ) {
            return 'Windows';
        } elseif ( strpos( $os, 'darwin' ) !== false || PHP_OS_FAMILY === 'Darwin' ) {
            return 'macOS';
        } else {
            // For Linux/Unix, try to get more info with uname to detect containerized environments
            $uname = shell_exec( 'uname -a 2>/dev/null' );
            if ( $uname ) {
                $uname_lower = strtolower( $uname );
                // Check for macOS indicators in uname output (for Docker/containers running on macOS)
                if ( strpos( $uname_lower, 'darwin' ) !== false ) {
                    return 'macOS';
                }
                // Check for Windows indicators (WSL, etc.)
                if ( strpos( $uname_lower, 'microsoft' ) !== false || strpos( $uname_lower, 'wsl' ) !== false ) {
                    return 'Windows';
                }
            }
            return 'Linux';
        }
    }

    private function get_platform() {
        return $this->detect_platform();
    }

    private function get_common_executable_paths() {
        $platform = $this->get_platform();
        $paths = array();

        switch ( $platform ) {
            case 'macOS':
                $paths = array(
                    '/opt/homebrew/bin',          // Homebrew on Apple Silicon
                    '/usr/local/bin',             // Homebrew on Intel, manual installs
                    '/usr/bin',                   // System package manager
                    '/opt/local/bin'              // MacPorts
                );
                break;
            case 'Linux':
                $paths = array(
                    '/usr/bin',
                    '/usr/local/bin',
                    '/snap/bin'                   // Snap packages
                );
                break;
            case 'Windows':
                $paths = array(
                    'C:\\Windows\\System32',
                    'C:\\Program Files\\Git\\cmd'
                );
                break;
        }

        return $paths;
    }

    private function find_node_executable() {
        $platform = $this->get_platform();
        $paths = $this->get_common_executable_paths();

        // Add Node.js specific paths
        if ( $platform === 'macOS' ) {
            $paths[] = '/usr/local/opt/node/bin';     // Homebrew linked
        } elseif ( $platform === 'Linux' ) {
            $paths[] = '/opt/node/bin';
        } elseif ( $platform === 'Windows' ) {
            $paths[] = 'C:\\Program Files\\nodejs';
            $paths[] = 'C:\\Program Files (x86)\\nodejs';
            $paths[] = 'C:\\nodejs';
        }

        // Add NVM paths for Unix-like systems - search common user directories
        if ( $platform === 'macOS' || $platform === 'Linux' ) {
            $base_dirs = array();
            if ( $platform === 'macOS' ) {
                // Common user directories on macOS
                $base_dirs = glob( '/Users/*', GLOB_ONLYDIR );
            } else {
                // Common user directories on Linux
                $base_dirs = glob( '/home/*', GLOB_ONLYDIR );
            }

            foreach ( $base_dirs as $user_dir ) {
                $nvm_paths = glob( $user_dir . '/.nvm/versions/node/*/bin' );
                if ( $nvm_paths ) {
                    $paths = array_merge( $paths, $nvm_paths );
                }
            }
        }

        return $this->find_executable_in_paths( 'node', $paths );
    }

    private function find_npm_executable() {
        $platform = $this->get_platform();
        $paths = $this->get_common_executable_paths();

        // Add npm specific paths (usually same as Node.js)
        if ( $platform === 'macOS' ) {
            $paths[] = '/usr/local/opt/node/bin';     // Homebrew linked
        } elseif ( $platform === 'Linux' ) {
            $paths[] = '/opt/node/bin';
        } elseif ( $platform === 'Windows' ) {
            $paths[] = 'C:\\Program Files\\nodejs';
            $paths[] = 'C:\\Program Files (x86)\\nodejs';
            $paths[] = 'C:\\nodejs';
        }

        // Add NVM paths for Unix-like systems - search common user directories
        if ( $platform === 'macOS' || $platform === 'Linux' ) {
            $base_dirs = array();
            if ( $platform === 'macOS' ) {
                // Common user directories on macOS
                $base_dirs = glob( '/Users/*', GLOB_ONLYDIR );
            } else {
                // Common user directories on Linux
                $base_dirs = glob( '/home/*', GLOB_ONLYDIR );
            }

            foreach ( $base_dirs as $user_dir ) {
                $nvm_paths = glob( $user_dir . '/.nvm/versions/node/*/bin' );
                if ( $nvm_paths ) {
                    $paths = array_merge( $paths, $nvm_paths );
                }
            }
        }

        return $this->find_executable_in_paths( 'npm', $paths );
    }

    private function find_git_executable() {
        $platform = $this->get_platform();
        $paths = $this->get_common_executable_paths();

        // Add Git specific paths
        if ( $platform === 'Windows' ) {
            $paths[] = 'C:\\Program Files\\Git\\bin';
            $paths[] = 'C:\\Program Files (x86)\\Git\\bin';
        }

        return $this->find_executable_in_paths( 'git', $paths );
    }

    private function find_executable_in_paths( $executable_name, $search_paths ) {
        $platform = $this->get_platform();

        // Add appropriate extension for Windows
        if ( $platform === 'Windows' ) {
            if ( $executable_name === 'npm' ) {
                $executable_name .= '.cmd';
            } else {
                $executable_name .= '.exe';
            }
        }

        foreach ( $search_paths as $dir ) {
            $full_path = rtrim( $dir, '/' ) . '/' . $executable_name;
            if ( $platform === 'Windows' ) {
                $full_path = rtrim( $dir, '\\' ) . '\\' . $executable_name;
            }

            if ( file_exists( $full_path ) && is_executable( $full_path ) ) {
                // Resolve symlinks to get the actual executable
                $real_path = realpath( $full_path );
                return $real_path ? $real_path : $full_path;
            }
        }

        return false;
    }

    private function get_node_common_paths() {
        $paths = array();
        $executable_path = $this->find_node_executable();
        if ( $executable_path ) {
            $paths[] = $executable_path;
        }
        return $paths;
    }

    private function get_npm_common_paths() {
        $paths = array();
        $executable_path = $this->find_npm_executable();
        if ( $executable_path ) {
            $paths[] = $executable_path;
        }
        return $paths;
    }

    private function check_requirement( $key ) {
        if ( ! isset( $this->sections[$key] ) ) {
            return false;
        }

        $check_function = $this->sections[$key]['check_function'];
        return call_user_func( $check_function );
    }

    private function check_git() {
        return $this->check_tool_with_enhanced_path( 'git', 'git version' );
    }

    private function check_nodejs() {
        return $this->check_tool_with_enhanced_path( 'node', 'v' );
    }

    private function check_tool_with_enhanced_path( $tool_name, $version_prefix ) {
        // Build comprehensive PATH with all possible installation locations
        $enhanced_paths = array(
            '/opt/homebrew/bin',           // Homebrew Apple Silicon
            '/usr/local/bin',              // Homebrew Intel
            '/usr/bin',                    // System
            '/opt/local/bin',              // MacPorts
            '/usr/local/opt/node/bin',     // Homebrew linked Node.js
            '/usr/local/opt/git/bin'       // Homebrew linked Git
        );

        // Add paths from shell configs
        $shell_paths = $this->get_paths_from_shell_configs();
        $enhanced_paths = array_merge( $enhanced_paths, $shell_paths );

        // Add all possible NVM paths
        $user_dirs = glob( '/Users/*', GLOB_ONLYDIR );
        if ( $user_dirs ) {
            foreach ( $user_dirs as $user_dir ) {
                $nvm_paths = glob( $user_dir . '/.nvm/versions/node/*/bin' );
                if ( $nvm_paths ) {
                    $enhanced_paths = array_merge( $enhanced_paths, $nvm_paths );
                }
            }
        }

        // Build enhanced PATH
        $enhanced_path = implode( ':', array_unique( $enhanced_paths ) ) . ':' . getenv( 'PATH' );

        // Try with enhanced PATH
        $output = shell_exec( "PATH='$enhanced_path' $tool_name --version 2>&1" );

        if ( $output && strpos( trim( $output ), $version_prefix ) === 0 ) {
            return trim( $output ); // Return version string for display
        }

        return false;
    }

    private function get_paths_from_shell_configs() {
        $paths = array();

        // Common shell config files to check
        $config_files = array(
            '/Users/alex/.zshrc',
            '/Users/alex/.bashrc',
            '/Users/alex/.bash_profile',
            '/Users/alex/.profile'
        );

        foreach ( $config_files as $config_file ) {
            if ( file_exists( $config_file ) && is_readable( $config_file ) ) {
                $content = file_get_contents( $config_file );
                if ( $content ) {
                    // Look for PATH exports and NVM initialization
                    $lines = explode( "\n", $content );
                    foreach ( $lines as $line ) {
                        $line = trim( $line );

                        // Skip comments
                        if ( strpos( $line, '#' ) === 0 ) {
                            continue;
                        }

                        // Look for PATH exports
                        if ( preg_match( '/export\s+PATH=.*?([\/\w\-\.]+\/bin)/', $line, $matches ) ) {
                            $paths[] = $matches[1];
                        }

                        // Look for PATH prepends
                        if ( preg_match( '/PATH=([\/\w\-\.]+\/bin):\$PATH/', $line, $matches ) ) {
                            $paths[] = $matches[1];
                        }

                        // Look for NVM initialization
                        if ( strpos( $line, 'nvm.sh' ) !== false ) {
                            // If NVM is sourced, try to find current NVM node
                            $nvm_current = shell_exec( 'bash -c "source ~/.nvm/nvm.sh && nvm current 2>/dev/null"' );
                            if ( $nvm_current ) {
                                $version = trim( $nvm_current );
                                $nvm_bin = "/Users/alex/.nvm/versions/node/$version/bin";
                                if ( is_dir( $nvm_bin ) ) {
                                    $paths[] = $nvm_bin;
                                }
                            }
                        }
                    }
                }
            }
        }

        return array_unique( $paths );
    }

    private function check_npm() {
        $result = $this->check_tool_with_enhanced_path( 'npm', '' );
        // NPM version starts with a number, not a letter like node's 'v'
        return $result && is_numeric( trim( $result )[0] ) ? $result : false;
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

        // Check if this is the WordPress develop repository
        $output = shell_exec( 'cd ' . ABSPATH . ' && git remote get-url origin 2>&1' );
        $is_wp_repo = $output && ( strpos( $output, 'wordpress-develop' ) !== false || strpos( $output, 'WordPress/wordpress-develop' ) !== false );

        if ( ! $is_wp_repo ) {
            return false;
        }

        // Verify the /src directory exists (important for WordPress core development)
        $src_path = ABSPATH . 'src';
        return is_dir( $src_path );
    }

    private function check_wporg_account() {
        $username = get_user_meta( get_current_user_id(), 'ctw_wporg_username', true );
        return ! empty( $username );
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

    private function get_wp_repo_instructions() {
        return '<p><strong>Setup WordPress Core GitHub Repository:</strong></p>
                <ol>
                    <li>Clone the repository: <code>git clone https://github.com/WordPress/wordpress-develop.git</code></li>
                    <li>Navigate to directory: <code>cd wordpress-develop</code></li>
                    <li>Install dependencies: <code>npm install</code></li>
                    <li>Set up local environment: <code>npm run build:dev</code></li>
                </ol>
                <p><a href="https://make.wordpress.org/core/handbook/contribute/git/" target="_blank" class="button">WordPress Git Workflow Guide</a></p>';
    }

    private function get_wporg_account_instructions() {
        return '<p><strong>Create Your WordPress.org Account:</strong></p>
                <ol>
                    <li>Visit <a href="https://login.wordpress.org/register" target="_blank">WordPress.org Registration</a></li>
                    <li>Choose a memorable username (this will be your contributor identity)</li>
                    <li>Complete the registration process</li>
                    <li>Come back here and add your username in the Account Setup section above</li>
                </ol>
                <p><strong>Why you need this:</strong> Your WordPress.org account allows you to submit patches to Trac, comment on tickets, and participate in contributor discussions.</p>';
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

        update_user_meta( get_current_user_id(), 'ctw_wporg_username', $username );
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