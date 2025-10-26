#!/bin/bash
#
# PHPUnit Setup for Local by Flywheel
# Run: bash setup-phpunit-local.sh
#

set -e

echo "🚀 Setting up PHPUnit tests for Local by Flywheel..."
echo ""

# Get the site name from the path
SITE_NAME="url-image-importer"
PLUGIN_DIR=$(pwd)

echo "📋 Site: $SITE_NAME"
echo "📂 Plugin: $PLUGIN_DIR"
echo ""

# Check if we're in Local environment
if [[ ! "$PLUGIN_DIR" =~ "Local Sites" ]]; then
    echo "❌ Error: This doesn't look like a Local by Flywheel installation"
    echo "   Expected path to contain 'Local Sites'"
    exit 1
fi

# Install composer dependencies if not already installed
if [ ! -d "vendor" ]; then
    echo "📦 Installing Composer dependencies..."
    composer install
fi

# Create a custom wp-tests-config.php for Local
echo "🔧 Creating test configuration..."

# Find Local's database credentials
DB_NAME="local"
DB_USER="root"
DB_PASSWORD="root"

# Local by Flywheel MySQL uses a Unix socket, not TCP
# Find the socket file
SOCKET_FILE=$(find ~/Library/Application\ Support/Local -name "mysqld.sock" 2>/dev/null | head -1)
if [ -n "$SOCKET_FILE" ]; then
    DB_HOST="localhost:$SOCKET_FILE"
    echo "🔍 Found Local MySQL socket: $SOCKET_FILE"
else
    # Fallback to localhost
    DB_HOST="localhost"
    echo "⚠️  Could not find MySQL socket, using localhost"
fi

# Find WordPress root (go up from plugins directory)
WP_ROOT="$PLUGIN_DIR/../../../"
WP_ROOT=$(cd "$WP_ROOT" && pwd)

echo "🔍 WordPress root: $WP_ROOT"

if [ ! -f "$WP_ROOT/wp-settings.php" ]; then
    echo "❌ Error: Could not find WordPress installation at $WP_ROOT"
    echo "   Looking for wp-settings.php"
    exit 1
fi

# Create temporary directory for WordPress test files
WP_TESTS_DIR="$HOME/.wp-tests"
mkdir -p "$WP_TESTS_DIR"

# Download WordPress test suite if not exists
if [ ! -d "$WP_TESTS_DIR/includes" ]; then
    echo "📥 Downloading WordPress test suite..."
    svn co --quiet https://develop.svn.wordpress.org/trunk/tests/phpunit/includes/ "$WP_TESTS_DIR/includes"
    svn co --quiet https://develop.svn.wordpress.org/trunk/tests/phpunit/data/ "$WP_TESTS_DIR/data"
fi

# Create wp-tests-config.php
cat > "$WP_TESTS_DIR/wp-tests-config.php" << EOF
<?php
/* Path to the WordPress codebase you'd like to test. */
define( 'ABSPATH', '$WP_ROOT/' );

// Test Database
define( 'DB_NAME', '${DB_NAME}' );
define( 'DB_USER', '${DB_USER}' );
define( 'DB_PASSWORD', '${DB_PASSWORD}' );
define( 'DB_HOST', '${DB_HOST}' );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );

\$table_prefix = 'wptests_';

define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Test Blog' );

define( 'WP_PHP_BINARY', 'php' );
define( 'WPLANG', '' );
EOF

# Update bootstrap.php to use the config
cat > tests/bootstrap.php << 'EOF'
<?php
/**
 * PHPUnit bootstrap file for Local by Flywheel
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
    $_tests_dir = getenv( 'HOME' ) . '/.wp-tests';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
    echo "Could not find $_tests_dir/includes/functions.php\n";
    echo "Run: bash setup-phpunit-local.sh\n";
    exit( 1 );
}

require_once $_tests_dir . '/includes/functions.php';

function _manually_load_plugin() {
    require dirname( dirname( __FILE__ ) ) . '/url-image-importer.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

require $_tests_dir . '/includes/bootstrap.php';
EOF

echo ""
echo "✅ Setup complete!"
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "To run tests:"
echo ""
echo "  composer test          # Run all tests"
echo "  composer test-watch    # Run tests when files change"
echo ""
echo "Or directly:"
echo "  ./vendor/bin/phpunit"
echo "  ./vendor/bin/phpunit tests/test-security.php"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "⚠️  Note: Tests will run using Local's database."
echo "    Make sure Local is running!"
