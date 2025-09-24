<?php
/**
 * Plugin Name: Contribute to WordPress
 * Plugin URI: https://github.com/wordpress/wordpress-develop
 * Description: Helps you setup your development environment for contributing to WordPress.
 * Version: 1.0.0
 * Author: WordPress Contributors
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
    private $platform = null;
    private $current_overrides = null;

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action( 'wp_ajax_ctw_check_requirements', array( $this, 'ajax_check_requirements' ) );
        add_action( 'wp_ajax_ctw_check_single_requirement', array( $this, 'ajax_check_single_requirement' ) );
        add_action( 'wp_ajax_ctw_verify_username', array( $this, 'ajax_verify_username' ) );
        add_action( 'wp_ajax_ctw_set_platform', array( $this, 'ajax_set_platform' ) );
        add_action( 'wp_ajax_ctw_get_stages', array( $this, 'ajax_get_stages' ) );

        $this->init_sections();
    }

    private function init_sections() {
        $this->sections = array(
            'git' => array(
                'name' => 'Git',
                'explanation' => 'The version control system',
                'explanation_link' => 'https://en.wikipedia.org/wiki/Git',
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
                'explanation' => 'The JavaScript runtime',
                'explanation_link' => 'https://en.wikipedia.org/wiki/Node.js',
                'description' => 'JavaScript runtime for building modern WordPress features',
                'check_function' => array( $this, 'check_nodejs' ),
                'instructions' => $this->get_nodejs_instructions(),
                'needed_for' => 'Required for Core Asset Building and Block development to build and test JavaScript features, compile CSS/JS assets, and develop Gutenberg blocks.'
            ),
            'npm' => array(
                'name' => 'npm',
                'explanation' => 'The JavaScript package manager',
                'explanation_link' => 'https://en.wikipedia.org/wiki/Npm_(software)',
                'description' => 'Package manager for Node.js dependencies',
                'check_function' => array( $this, 'check_npm' ),
                'instructions' => $this->get_npm_instructions(),
                'needed_for' => 'Required for Core Asset Building and Block development to install and manage JavaScript packages and build tools for WordPress core assets.'
            ),
            'composer' => array(
                'name' => 'Composer',
                'description' => 'PHP dependency manager for WordPress development',
                'explanation' => 'The PHP package manager',
                'explanation_link' => 'https://en.wikipedia.org/wiki/Composer_(software)',
               'check_function' => array( $this, 'check_composer' ),
                'instructions' => $this->get_composer_instructions(),
                'needed_for' => 'Optional for PHP development to manage dependencies and run WordPress development tools.'
            ),
            'gutenberg' => array(
                'name' => 'Gutenberg Development version',
                'description' => 'Gutenberg plugin cloned from GitHub for block development',
                'explanation' => 'The Block Editor',
                'explanation_link' => 'https://github.com/WordPress/gutenberg',
                'check_function' => array( $this, 'check_gutenberg_plugin' ),
                'instructions' => $this->get_gutenberg_instructions(),
                'needed_for' => 'Block development happens in the Gutenberg plugin repository.'
            ),
            'plugin_theme_git' => array(
                'name' => 'Plugin and Theme Development',
                'description' => 'At least one plugin or theme under git version control',
                'check_function' => array( $this, 'check_plugin_theme_git' ),
                'instructions' => $this->get_plugin_theme_git_instructions(),
                'needed_for' => 'Git-managed plugins and themes enable proper version control, collaboration, and contribution workflows for WordPress development.'
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
        add_menu_page(
            'Contribute to WordPress',
            'Contribute to WordPress',
            'manage_options',
            'contribute-to-wordpress',
            array( $this, 'admin_page' ),
            'dashicons-wordpress',
            30
        );
    }

    public function enqueue_scripts( $hook ) {
        if ( $hook !== 'toplevel_page_contribute-to-wordpress' ) {
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
            <h1>Contribute to WordPress</h1>

            <!-- Contribution Stages -->
            <div class="contribution-stages">
                <h2>Thank you for wanting to contribute to WordPress!</h2>
                <p>Find below an analysis of your environment so that you can get started with development:</p>

                <div id="contribution-stages-container">
                    <?php $this->render_contribution_stages(); ?>
                </div>
            </div>

            <!-- Development Environment Status -->
            <div class="checklist-section">
                <h2>💻 Development Environment</h2>
                <p class="platform-info">
                    <strong>Platform:</strong>
                    <span id="current-platform"><?php echo esc_html( $this->get_platform() ); ?></span>
                    <a href="#" onclick="showPlatformOverride(); return false;" class="platform-link" id="platform-wrong-link">Change</a>
                    <select id="platform-override" onchange="overridePlatform(this.value)" class="platform-select">
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
                            <a href="#" onclick="editUsername(); return false;" class="username-link" id="edit-username-link">(Change)</a>
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
                                        <button type="button" id="cancel-edit" class="button cancel-button">Cancel</button>
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
                'title' => 'GitHub Web Interface Contributions',
                'description' => 'Edit files directly on GitHub without local development setup',
                'icon' => '🌐',
                'requirements' => array()
            ),
            'php' => array(
                'title' => 'PHP Core Development',
                'description' => 'Contribute to WordPress core PHP code, themes, and backend functionality',
                'icon' => '⚙️',
                'requirements' => array( 'git', 'wp_repo' ),
                'optional' => array( 'composer' )
            ),
            'assets' => array(
                'title' => 'Core Asset Building',
                'description' => 'Build and compile WordPress core CSS, JavaScript, and other frontend assets',
                'icon' => '🎨',
                'requirements' => array( 'git', 'wp_repo', 'node', 'npm' )
            ),
            'blocks' => array(
                'title' => 'Block Development',
                'description' => 'Contribute to Gutenberg blocks and modern WordPress JavaScript development',
                'icon' => '🧱',
                'requirements' => array( 'git', 'wp_repo', 'node', 'npm', 'gutenberg' )
            ),
            'plugins_themes' => array(
                'title' => 'Plugin & Theme Development',
                'description' => 'Develop and contribute to WordPress plugins and themes using git version control',
                'icon' => '🔌',
                'requirements' => array( 'git', 'plugin_theme_git' )
            ),
            'contribute' => array(
                'title' => 'Contribute Back',
                'description' => 'Submit patches, create tickets, and participate in the WordPress community',
                'icon' => '🚀',
                'requirements' => array( 'wporg_account' )
            )
        );
    }

    private function render_contribution_stages() {
        $stages = $this->get_stages_data();

        foreach ( $stages as $stage_key => $stage ) {
            // Show stages with undetermined status initially
            $this->render_single_stage( $stage_key, $stage, array(), true );
        }
    }

    private function render_single_stage( $stage_key, $stage, $overrides = array(), $undetermined = false ) {
        $missing_requirements = array();
        $missing_optional = array();
        $stage_ready = true;

        if ( $undetermined ) {
            // Show undetermined status during initial load
            $status_class = 'undetermined';
            $status_icon = '⏳';
        } else {
            // Check required items
            foreach ( $stage['requirements'] as $req ) {
                // Check debug override first, then real state
                if ( isset( $overrides[$req] ) ) {
                    $requirement_met = $overrides[$req];
                } else {
                    $requirement_met = $this->check_requirement( $req );
                }

                if ( ! $requirement_met ) {
                    $missing_requirements[] = $this->sections[$req]['name'];
                    $stage_ready = false;
                }
            }

            // Check optional items
            if ( isset( $stage['optional'] ) ) {
                foreach ( $stage['optional'] as $opt ) {
                    // Check debug override first, then real state
                    if ( isset( $overrides[$opt] ) ) {
                        $requirement_met = $overrides[$opt];
                    } else {
                        $requirement_met = $this->check_requirement( $opt );
                    }

                    if ( ! $requirement_met ) {
                        $missing_optional[] = $this->sections[$opt]['name'];
                    }
                }
            }

            $status_class = $stage_ready ? 'available' : 'not_ready';
            $status_icon = $stage_ready ? '✅' : '⭕';
        }

        ?>
        <div class="stage-item <?php echo esc_attr( $status_class ); ?>" data-stage="<?php echo esc_attr( $stage_key ); ?>">
            <div class="stage-content">
                <div class="stage-icon"><?php echo esc_html( $stage['icon'] ); ?></div>
                <div class="stage-details">
                    <h3 class="stage-title <?php echo esc_attr( $status_class ); ?>">
                        <?php echo esc_html( $status_icon . ' ' . $stage['title'] ); ?>
                    </h3>
                    <p class="stage-description"><?php echo esc_html( $stage['description'] ); ?></p>

                    <?php if ( $undetermined ) : ?>
                        <p class="stage-checking">
                            <span class="dashicons dashicons-update"></span>
                            Checking requirements...
                        </p>
                    <?php elseif ( ! $stage_ready && ! empty( $missing_requirements ) ) : ?>
                        <p class="stage-missing">
                            <strong>Missing:</strong> <?php echo esc_html( implode( ', ', $missing_requirements ) ); ?>
                        </p>
                    <?php endif; ?>

                    <?php if ( ! $undetermined && ! empty( $missing_optional ) ) : ?>
                        <p class="stage-optional">
                            <strong>Optional:</strong> <?php echo esc_html( implode( ', ', $missing_optional ) ); ?> (recommended but not required)
                        </p>
                    <?php endif; ?>

                    <?php if ( $stage_key === 'plugins_themes' && $stage_ready ) : ?>
                        <?php
                        $git_repos = $this->get_plugin_theme_git_repos();
                        if ( ! empty( $git_repos ) ) :
                        ?>
                            <div class="git-repos-section">
                                <p class="git-repos-title">Ready for development:</p>

                                <?php
                                $active_repos = array_filter( $git_repos, function( $repo ) { return $repo['active']; } );
                                $inactive_repos = array_filter( $git_repos, function( $repo ) { return ! $repo['active']; } );
                                ?>

                                <?php if ( ! empty( $active_repos ) ) : ?>
                                    <details open class="git-repos-details">
                                        <summary class="git-repos-summary active">
                                            Active (<?php echo count( $active_repos ); ?>)
                                        </summary>
                                        <ul class="git-repos-list">
                                            <?php foreach ( $active_repos as $repo ) : ?>
                                                <li class="git-repos-item">
                                                    <strong><?php echo esc_html( ucfirst( $repo['type'] ) ); ?>:</strong>
                                                    <?php echo esc_html( $repo['name'] ); ?>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </details>
                                <?php endif; ?>

                                <?php if ( ! empty( $inactive_repos ) ) : ?>
                                    <details class="git-repos-details">
                                        <summary class="git-repos-summary inactive">
                                            Inactive (<?php echo count( $inactive_repos ); ?>)
                                        </summary>
                                        <ul class="git-repos-list">
                                            <?php foreach ( $inactive_repos as $repo ) : ?>
                                                <li class="git-repos-item">
                                                    <strong><?php echo esc_html( ucfirst( $repo['type'] ) ); ?>:</strong>
                                                    <?php echo esc_html( $repo['name'] ); ?>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </details>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }

    private function render_environment_checks() {
        foreach ( $this->sections as $key => $section ) {
            // Skip actual checking on page load - will be done via AJAX
            $status_class = 'loading';
            $icon_class = 'dashicons-update';
            $status_text = 'Checking...';
            $status_badge_class = 'loading';

            // For initial load, assume tools are not available and show instructions
            $show_instructions_initially = true;

            ?>
            <div class="checklist-item <?php echo esc_attr( $status_class ); ?>" data-section="<?php echo esc_attr( $key ); ?>">
                <div class="checkbox-wrapper">
                    <span class="dashicons <?php echo esc_attr( $icon_class ); ?>"></span>
                    <strong><?php echo esc_html( $section['name'] ); ?></strong>
                    <?php if ( ! empty( $section['explanation'] ) ) : ?>
                        <span>—</span>
                        <span class="tool-explanation"><?php echo esc_html( $section['explanation'] ); ?></span>
                        <?php if ( ! empty( $section['explanation_link'] ) ) : ?>
                            <a href="<?php echo esc_url( $section['explanation_link'] ); ?>" target="_blank" title="More information">?</a>
                        <?php endif; ?>
                    <?php endif; ?>
                    <span class="status-badge status-badge-clickable <?php echo esc_attr( $status_badge_class ); ?>"
                          onclick="toggleDebugMode('<?php echo esc_attr( $key ); ?>')"
                          title="Click to toggle debug mode">
                        <?php echo esc_html( $status_text ); ?>
                    </span>
                </div>

                <?php if ( $show_instructions_initially ) : ?>
                    <div class="instructions" id="instructions-<?php echo esc_attr( $key ); ?>">
                        <div class="instructions-section">
                            <p class="instructions-text">
                                <strong>Why you need this:</strong> <?php echo esc_html( $section['needed_for'] ); ?>
                            </p>
                        </div>

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

    private function detect_platform() {
        if ( ! $this->platform ) {
            $os = strtolower( PHP_OS );

            if ( strpos( $os, 'win' ) !== false || PHP_OS_FAMILY === 'Windows' ) {
                $this->platform = 'Windows';
            } elseif ( strpos( $os, 'darwin' ) !== false || PHP_OS_FAMILY === 'Darwin' ) {
                $this->platform =  'macOS';
            } else {
                // For Linux/Unix, try to get more info with uname to detect containerized environments
                $uname = shell_exec( 'uname -a 2>/dev/null' );
                if ( $uname ) {
                    $uname_lower = strtolower( $uname );
                    // Check for macOS indicators in uname output (for Docker/containers running on macOS)
                    if ( strpos( $uname_lower, 'darwin' ) !== false ) {
                        $this->platform =  'macOS';
                    } elseif ( strpos( $uname_lower, 'microsoft' ) !== false || strpos( $uname_lower, 'wsl' ) !== false ) {
                        $this->platform =  'Windows';
                    }
                }
            }
            if ( ! $this->platform ) {
                $this->platform =  'Linux';
            }
        }

        return $this->platform;
    }

    private function get_platform() {
        if ( isset( $this->current_overrides['platform'] ) ) {
            return $this->current_overrides['platform'];
        }
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

    private function check_requirement( $key, $overrides = array() ) {
        if ( ! isset( $this->sections[$key] ) ) {
            return false;
        }

        // Check debug override first
        if ( isset( $overrides[$key] ) ) {
            return $overrides[$key];
        }

        $this->current_overrides = $overrides;

        $check_function = $this->sections[$key]['check_function'];
        return call_user_func( $check_function );
    }

    private function check_tool( $tool_name, $additional_paths = array() ) {
        static $search_paths, $shell_paths;
        if ( ! isset( $search_paths ) ) {
            $search_paths = $this->get_common_executable_paths();
        }
        if ( ! isset( $shell_paths ) ) {
            $shell_paths = $this->get_paths_from_shell_configs();
        }
        $search_paths = array_unique( array_merge( $search_paths, $shell_paths, $additional_paths ) );

        $path = null;
        $version = null;
        $note = null;

        $enhanced_path = implode( ':', array_unique( $search_paths ) ) . ':' . getenv( 'PATH' );
        $output = shell_exec( "PATH='$enhanced_path' $tool_name --version 2>&1" );

        if ( $output ) {
            $version = $this->extract_semver( trim( $output ) );
        } else {
            return false;
        }

        foreach ( $search_paths as $search_path ) {
            $full_path = rtrim( $search_path, '/' ) . '/' . $tool_name;
            if ( file_exists( $full_path ) && is_executable( $full_path ) ) {
               $path = $full_path;
               break;
           }
        }

        if ( ! $path ) {
            foreach ( $search_paths as $search_path ) {
                $output = shell_exec( "PATH='$search_path' $tool_name --version 2>&1" );
                if ( $output ) {
                    $path = $search_path;
                    $this->platform = 'WordPress Studio';
                    break;
                }
            }
        }


        return compact( 'path', 'version', 'note' );
    }

    private function extract_semver( $version_output ) {
        // Extract semantic version (major.minor.patch) from various tool outputs
        if ( preg_match( '/(\d+\.\d+\.\d+)/', $version_output, $matches ) ) {
            return $matches[1];
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

    private function check_git() {
        $result = $this->check_tool( 'git' );
        return $result ? 'Git ' . $result['version'] : false;
    }

    private function check_nodejs() {
        $nvm_paths = array();

        $user_dirs = glob( '/Users/*', GLOB_ONLYDIR );
        if ( $user_dirs ) {
            foreach ( $user_dirs as $user_dir ) {
                $paths = glob( $user_dir . '/.nvm/versions/node/*/bin' );
                if ( $paths ) {
                    $nvm_paths = array_merge( $nvm_paths, $paths );
                }
            }
        }

        $result = $this->check_tool( 'node', $nvm_paths );
        return $result ? 'Node.js ' . $result['version'] : false;
    }

    private function check_npm() {
        $nvm_paths = array();

        $user_dirs = glob( '/Users/*', GLOB_ONLYDIR );
        if ( $user_dirs ) {
            foreach ( $user_dirs as $user_dir ) {
                $paths = glob( $user_dir . '/.nvm/versions/node/*/bin' );
                if ( $paths ) {
                    $nvm_paths = array_merge( $nvm_paths, $paths );
                }
            }
        }

        $result = $this->check_tool( 'npm', $nvm_paths );
        return $result ? 'npm ' . $result['version'] : false;
    }

    private function check_composer() {
        $result = $this->check_tool( 'composer' );
        return $result ? 'Composer ' . $result['version'] : false;
    }

    private function check_gutenberg_plugin() {
        $git_repos = $this->get_plugin_theme_git_repos();

        // Look for Gutenberg in the git repositories
        foreach ( $git_repos as $repo ) {
            if ( $repo['type'] === 'plugin' && $repo['folder'] === 'gutenberg' ) {
                return $repo['active'] ? 'Gutenberg git repository (Active)' : 'Gutenberg git repository (Inactive)';
            }
        }

        return false;
    }

    private function check_wordpress_repository() {
        $has__index = false;
        $has_index_marker = false;

        if ( file_exists( ABSPATH . '_index.php' ) ) {
            $has__index = true;
        }

        $index_file = ABSPATH . 'index.php';
        if ( file_exists( $index_file ) ) {
            $content = file_get_contents( $index_file );
            if ( $content && strpos( $content, 'Note: this file exists only to remind developers to build the assets' ) !== false ) {
                $has_index_marker = true;
            }
        }

        // We need both indicators to confirm this is the WordPress develop repository
        return $has__index && $has_index_marker;
    }

    private function check_wporg_account() {
        $username = get_user_meta( get_current_user_id(), 'ctw_wporg_username', true );
        return ! empty( $username );
    }

    private function check_plugin_theme_git() {
        $git_repos = $this->get_plugin_theme_git_repos();

        if ( ! empty( $git_repos ) ) {
            $count = count( $git_repos );
            return $count . ' git ' . ( $count === 1 ? 'repository' : 'repositories' );
        }

        return false;
    }

    private function get_plugin_theme_git_repos() {
        $git_repos = array();

        // Check plugin directories with git repositories
        $plugin_dirs = glob( WP_PLUGIN_DIR . '/*', GLOB_ONLYDIR );
        foreach ( $plugin_dirs as $plugin_dir ) {
            if ( ! is_dir( $plugin_dir . '/.git' ) ) {
                continue;
            }

            $plugin_file = $this->find_main_plugin_file( $plugin_dir );
            if ( ! $plugin_file ) {
                continue;
            }

            if ( ! function_exists( 'get_plugin_data' ) ) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }

            $plugin_data = get_plugin_data( $plugin_file, false, false );
            if ( empty( $plugin_data['Name'] ) ) {
                continue;
            }

            $relative_path = basename( $plugin_dir ) . '/' . basename( $plugin_file );
            $git_repos[] = array(
                'type' => 'plugin',
                'name' => $plugin_data['Name'],
                'folder' => basename( $plugin_dir ),
                'file' => $relative_path,
                'active' => is_plugin_active( $relative_path ),
                'path' => $plugin_dir
            );
        }

        // Check theme directories with git repositories
        $theme_dirs = glob( get_theme_root() . '/*', GLOB_ONLYDIR );
        $current_theme = get_stylesheet();

        foreach ( $theme_dirs as $theme_dir ) {
            if ( ! is_dir( $theme_dir . '/.git' ) ) {
                continue;
            }

            $style_css = $theme_dir . '/style.css';
            if ( ! file_exists( $style_css ) ) {
                continue;
            }

            if ( ! function_exists( 'wp_get_theme' ) ) {
                require_once ABSPATH . 'wp-includes/theme.php';
            }

            $theme_slug = basename( $theme_dir );
            $theme_data = wp_get_theme( $theme_slug );

            if ( ! $theme_data->exists() ) {
                continue;
            }

            $git_repos[] = array(
                'type' => 'theme',
                'name' => $theme_data->get( 'Name' ),
                'folder' => $theme_slug,
                'active' => ( $theme_slug === $current_theme ),
                'path' => $theme_dir
            );
        }

        return $git_repos;
    }

    private function find_main_plugin_file( $plugin_dir ) {
        $files = glob( $plugin_dir . '/*.php' );

        foreach ( $files as $file ) {
            $contents = file_get_contents( $file, false, null, 0, 8192 );

            if ( strpos( $contents, '<?php' ) !== 0 ) {
                continue;
            }

            if ( preg_match( '/Plugin Name\s*:\s*(.+)/i', $contents ) ) {
                return $file;
            }
        }

        return false;
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

    private function get_composer_instructions() {
        $platform = $this->get_platform();

        switch ( $platform ) {
            case 'Windows':
                return '<p><strong>Install Composer on Windows:</strong></p>
                        <ol>
                            <li>Download Composer installer from <a href="https://getcomposer.org/download/" target="_blank">getcomposer.org</a></li>
                            <li>Run the installer and follow the setup wizard</li>
                            <li>Verify installation: <code>composer --version</code></li>
                        </ol>';
            case 'macOS':
                return '<p><strong>Install Composer on macOS:</strong></p>
                        <ol>
                            <li>Install via Homebrew: <code>brew install composer</code></li>
                            <li>Or download from <a href="https://getcomposer.org/download/" target="_blank">getcomposer.org</a></li>
                            <li>Verify installation: <code>composer --version</code></li>
                        </ol>';
            default:
                return '<p><strong>Install Composer on Linux:</strong></p>
                        <ol>
                            <li>Download and install: <code>curl -sS https://getcomposer.org/installer | php</code></li>
                            <li>Move to global location: <code>sudo mv composer.phar /usr/local/bin/composer</code></li>
                            <li>Verify installation: <code>composer --version</code></li>
                        </ol>';
        }
    }

    private function get_gutenberg_instructions() {
        return '<p><strong>Clone Gutenberg Plugin from GitHub:</strong></p>
                <ol>
                    <li>Navigate to your WordPress plugins directory: <code>cd ' . WP_PLUGIN_DIR . '</code></li>
                    <li>Clone the Gutenberg repository: <code>git clone https://github.com/WordPress/gutenberg.git</code></li>
                    <li>Navigate to the plugin directory: <code>cd gutenberg</code></li>
                    <li>Install dependencies: <code>npm install</code></li>
                    <li>Build the plugin: <code>npm run build</code></li>
                    <li>Activate the plugin in WordPress admin</li>
                </ol>
                <p><strong>Alternative:</strong> Use <code>npm run dev</code> instead of <code>npm run build</code> for development builds with watch mode.</p>';
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
                </ol>';
    }

    private function get_plugin_theme_git_instructions() {
        return '<p><strong>Set Up Git Repository for Plugin/Theme Development:</strong></p>
                <ol>
                    <li>Navigate to your plugin or theme directory in terminal</li>
                    <li>Initialize a git repository: <code>git init</code></li>
                    <li>Add your files: <code>git add .</code></li>
                    <li>Create your first commit: <code>git commit -m "Initial commit"</code></li>
                    <li>Optionally, connect to a remote repository on GitHub for collaboration</li>
                </ol>
                <p><strong>Alternative:</strong> Clone an existing plugin/theme repository from GitHub into your WordPress installation.</p>';
    }

    // AJAX handlers
    public function ajax_check_requirements() {
        check_ajax_referer( 'ctw_nonce', 'nonce' );

        $overrides = isset( $_POST['overrides'] ) ? $_POST['overrides'] : array();

        $results = array();
        foreach ( $this->sections as $key => $section ) {
            $results[$key] = $this->check_requirement( $key, $overrides );
        }

        wp_send_json_success( $results );
    }

    public function ajax_check_single_requirement() {
        check_ajax_referer( 'ctw_nonce', 'nonce' );

        $section_key = sanitize_text_field( $_POST['section'] );
        $overrides = isset( $_POST['overrides'] ) ? $_POST['overrides'] : array();

        if ( ! isset( $this->sections[$section_key] ) ) {
            wp_send_json_error( 'Invalid section' );
        }

        $this->current_overrides = $overrides;

        $result = $this->check_requirement( $section_key, $overrides );
        $version = $this->get_tool_version( $section_key );
        $instructions = $this->get_section_instructions( $section_key );

        wp_send_json_success( array(
            'status' => $result,
            'version' => $version,
            'instructions' => $instructions
        ) );
    }

    private function get_tool_version( $section_key ) {
        $check_function = $this->sections[$section_key]['check_function'];
        $result = call_user_func( $check_function );

        if ( is_string( $result ) ) {
            return $result;
        }

        return null;
    }

    private function get_section_instructions( $section_key ) {
        if ( ! isset( $this->sections[$section_key]['instructions'] ) ) {
            return '';
        }

        $instructions_method = $this->sections[$section_key]['instructions'];
        if ( is_callable( $instructions_method ) ) {
            return call_user_func( $instructions_method );
        }

        return '';
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
        $overrides = array();
        if ( isset( $_POST['overrides'] ) ) {
            $overrides = json_decode( stripslashes( $_POST['overrides'] ), true );
            if ( ! is_array( $overrides ) ) {
                $overrides = array();
            }
        }

        $stages = $this->get_stages_data();
        $stages_html = '';

        foreach ( $stages as $stage_key => $stage ) {
            ob_start();
            $this->render_single_stage( $stage_key, $stage, $overrides );
            $stages_html .= ob_get_clean();
        }

        wp_send_json_success( array( 'html' => $stages_html ) );
    }

}

// Initialize the plugin
new ContributeToWordPress();