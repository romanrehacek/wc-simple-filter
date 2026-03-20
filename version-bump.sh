#!/bin/bash

################################################################################
# WC Simple Filter - Complete Release Automation Script
#
# This script handles the COMPLETE release workflow:
# 1. Validates version format (semantic versioning)
# 2. Updates version in all files (plugin.php, readme.txt, package.json)
# 3. Shows what will be deployed (files to upload)
# 4. Commits changes to Git
# 5. Pushes to GitHub
# 6. Creates Git tag
# 7. Pushes tag (triggers GitHub Actions!)
#
# Usage: ./version-bump.sh 0.2.0
################################################################################

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Configuration
PLUGIN_FILE="wc-simple-filter.php"
README_FILE="readme.txt"
PACKAGE_FILE="package.json"

# Files and directories to EXCLUDE from WordPress.org deployment
EXCLUDE_PATTERNS=(
    '.git'
    '.github'
    '.gitignore'
    '.editorconfig'
    'node_modules'
    'tests'
    'composer.lock'
    'composer.json'
    'package.json'
    'package-lock.json'
    '.env'
    '.env.local'
    '.env.*.local'
    'phpcs.xml'
    'phpunit.xml'
    '.phpcs.xml.dist'
    '.eslintrc*'
    '.prettierrc*'
    'webpack.config.js'
    'gulpfile.js'
    'Gruntfile.js'
    'tsconfig.json'
    'vite.config.js'
    'rollup.config.js'
    '.DS_Store'
    'Thumbs.db'
    '*.log'
    'AGENTS.md'
    'TRANSLATION_DICTIONARY_SK.md'
    'RELEASE_GUIDE.md'
    'SETUP_SUMMARY_SK.md'
    'CI_CD_SETUP_COMPLETE.md'
    'README.md'
)

################################################################################
# Helper Functions
################################################################################

print_header() {
    echo -e "\n${BLUE}╔════════════════════════════════════════════════════════╗${NC}"
    echo -e "${BLUE}║${NC} $1"
    echo -e "${BLUE}╚════════════════════════════════════════════════════════╝${NC}\n"
}

print_step() {
    echo -e "${CYAN}→${NC} $1"
}

print_success() {
    echo -e "${GREEN}✓${NC} $1"
}

print_error() {
    echo -e "${RED}✗${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}⚠${NC} $1"
}

print_info() {
    echo -e "${YELLOW}ℹ${NC} $1"
}

confirm() {
    local prompt="$1"
    local response

    echo -ne "${YELLOW}${prompt}${NC} (y/n) "
    read -r response

    if [[ "$response" =~ ^[Yy]$ ]]; then
        return 0
    else
        return 1
    fi
}

validate_git_status() {
    if ! git rev-parse --git-dir > /dev/null 2>&1; then
        print_error "Not a Git repository"
        exit 1
    fi

    # Check if there are uncommitted changes (excluding version files we'll update)
    local uncommitted=$(git status --porcelain | grep -v "^ M $PLUGIN_FILE" | grep -v "^ M $README_FILE" | grep -v "^ M $PACKAGE_FILE" || true)

    if [ ! -z "$uncommitted" ]; then
        print_warning "You have uncommitted changes:"
        echo "$uncommitted"
        if ! confirm "Continue anyway?"; then
            print_error "Aborted by user"
            exit 1
        fi
    fi
}

validate_version_format() {
    local version="$1"

    if ! [[ $version =~ ^[0-9]+\.[0-9]+\.[0-9]+(-[a-zA-Z0-9]+)?$ ]]; then
        print_error "Invalid version format: $version"
        echo "Expected format: X.Y.Z (e.g., 0.2.0 or 1.0.0-beta)"
        exit 1
    fi
}

validate_files_exist() {
    if [ ! -f "$PLUGIN_FILE" ]; then
        print_error "$PLUGIN_FILE not found in current directory"
        exit 1
    fi

    if [ ! -f "$README_FILE" ]; then
        print_error "$README_FILE not found in current directory"
        exit 1
    fi
}

update_version_in_files() {
    local new_version="$1"
    local sed_cmd

    # Detect OS (macOS uses different sed syntax)
    if [[ "$OSTYPE" == "darwin"* ]]; then
        sed_cmd="sed -i ''"
    else
        sed_cmd="sed -i"
    fi

    print_step "Updating $PLUGIN_FILE..."

    # Update Version header
    eval $sed_cmd "\"s/ \* Version:.*/ * Version:     $new_version/\"" "$PLUGIN_FILE"

    # Update WC_SF_VERSION constant
    eval $sed_cmd "\"s/define( 'WC_SF_VERSION', '[^']*'/define( 'WC_SF_VERSION', '$new_version'/\"" "$PLUGIN_FILE"

    print_success "$PLUGIN_FILE updated (Version + Constant)"

    # Update readme.txt
    print_step "Updating $README_FILE..."
    eval $sed_cmd "\"s/^Stable tag:.*/Stable tag: $new_version/\"" "$README_FILE"
    print_success "$README_FILE updated (Stable tag)"

    # Update package.json if it exists
    if [ -f "$PACKAGE_FILE" ]; then
        print_step "Updating $PACKAGE_FILE..."
        if command -v jq &> /dev/null; then
            jq ".version = \"$new_version\"" "$PACKAGE_FILE" > "$PACKAGE_FILE.tmp" && mv "$PACKAGE_FILE.tmp" "$PACKAGE_FILE"
            print_success "$PACKAGE_FILE updated"
        else
            print_warning "jq not installed, skipping $PACKAGE_FILE (install: brew install jq)"
        fi
    fi
}

show_deployment_preview() {
    local excluded_files=""
    local include_count=0
    local exclude_count=0

    print_header "FILES THAT WILL BE DEPLOYED TO WORDPRESS.ORG"

    echo -e "${BLUE}Files to be included:${NC}"

    # Find all files and check against exclusions
    while IFS= read -r file; do
        local should_exclude=0

        # Check each exclusion pattern
        for pattern in "${EXCLUDE_PATTERNS[@]}"; do
            # Convert pattern to regex-like matching
            if [[ "$file" == "$pattern" ]] || [[ "$file" == *"/$pattern"* ]] || [[ "$file" == *"$pattern"* ]]; then
                should_exclude=1
                break
            fi
        done

        if [ $should_exclude -eq 0 ]; then
            echo -e "  ${GREEN}✓${NC} $file"
            ((include_count++))
        else
            ((exclude_count++))
        fi
    done < <(find . -type f -not -path '*/\.*' | sed 's|^\./||' | sort)

    echo ""
    echo -e "${BLUE}Files to be excluded (NOT uploaded):${NC}"

    while IFS= read -r file; do
        local should_exclude=0

        for pattern in "${EXCLUDE_PATTERNS[@]}"; do
            if [[ "$file" == "$pattern" ]] || [[ "$file" == *"/$pattern"* ]] || [[ "$file" == *"$pattern"* ]]; then
                should_exclude=1
                break
            fi
        done

        if [ $should_exclude -eq 1 ]; then
            echo -e "  ${RED}✗${NC} $file"
        fi
    done < <(find . -type f -not -path '*/\.*' | sed 's|^\./||' | sort)

    echo ""
    echo -e "${BLUE}Summary:${NC}"
    echo -e "  Files to upload: ${GREEN}$include_count${NC}"
    echo -e "  Files to exclude: ${RED}$exclude_count${NC}"
    echo ""
}

commit_and_push() {
    local new_version="$1"

    print_header "COMMITTING CHANGES"

    print_step "Adding files to Git..."
    git add "$PLUGIN_FILE" "$README_FILE"
    if [ -f "$PACKAGE_FILE" ]; then
        git add "$PACKAGE_FILE"
    fi
    print_success "Files staged"

    print_step "Creating commit..."
    git commit -m "chore: Bump version to $new_version"
    print_success "Commit created"

    print_step "Pushing to GitHub..."
    git push origin main
    print_success "Pushed to GitHub main branch"
}

create_and_push_tag() {
    local new_version="$1"
    local tag="v$new_version"

    print_header "CREATING RELEASE TAG"

    # Check if tag already exists
    if git rev-parse "$tag" >/dev/null 2>&1; then
        print_error "Tag $tag already exists!"
        if ! confirm "Delete and recreate tag?"; then
            print_error "Aborted by user"
            exit 1
        fi

        print_step "Deleting existing tag locally..."
        git tag -d "$tag"

        print_step "Deleting existing tag on GitHub..."
        git push origin --delete "$tag" 2>/dev/null || true
        print_success "Old tag deleted"
    fi

    print_step "Creating annotated tag: $tag..."
    git tag -a "$tag" -m "Release version $new_version"
    print_success "Tag created locally"

    print_step "Pushing tag to GitHub (this triggers GitHub Actions!)..."
    git push origin "$tag"
    print_success "Tag pushed to GitHub"

    echo ""
    echo -e "${GREEN}🚀 GitHub Actions workflow triggered!${NC}"
    echo -e "Monitor deployment at: ${CYAN}https://github.com/romanrehacek/wc-simple-filter/actions${NC}"
}

show_what_happens_next() {
    local new_version="$1"

    print_header "WHAT HAPPENS NEXT"

    echo -e "${BLUE}GitHub Actions will automatically:${NC}"
    echo "  1. ✓ Validate version format"
    echo "  2. ✓ Checkout WordPress.org SVN repository"
    echo "  3. ✓ Sync plugin files to SVN trunk (with exclusions)"
    echo "  4. ✓ Copy assets to SVN assets folder"
    echo "  5. ✓ Handle new/deleted files (svn add/rm)"
    echo "  6. ✓ Commit trunk to WordPress.org"
    echo "  7. ✓ Create SVN tag (tags/$new_version)"
    echo "  8. ✓ Create GitHub Release"
    echo ""
    echo -e "${BLUE}Timeline:${NC}"
    echo "  • GitHub Actions workflow: ~2-3 minutes"
    echo "  • Plugin appears on WordPress.org: ~1-2 hours (cache)"
    echo ""
    echo -e "${BLUE}Check status:${NC}"
    echo "  GitHub: https://github.com/romanrehacek/wc-simple-filter/actions"
    echo "  WordPress.org: https://wordpress.org/plugins/wc-simple-filter/"
    echo ""
}

################################################################################
# Main Script
################################################################################

main() {
    # Check arguments
    if [ -z "$1" ]; then
        print_header "WC SIMPLE FILTER - VERSION BUMP & RELEASE"
        echo -e "Usage: ${CYAN}./version-bump.sh VERSION${NC}"
        echo ""
        echo "Examples:"
        echo "  ${CYAN}./version-bump.sh 0.2.0${NC}   # Minor release"
        echo "  ${CYAN}./version-bump.sh 1.0.0${NC}   # Major release"
        echo "  ${CYAN}./version-bump.sh 1.0.1${NC}   # Patch release"
        echo ""
        echo "What this script does:"
        echo "  1. ✓ Validates version format (semantic versioning)"
        echo "  2. ✓ Updates version in all files"
        echo "  3. ✓ Shows deployment preview"
        echo "  4. ✓ Commits changes to Git"
        echo "  5. ✓ Pushes to GitHub"
        echo "  6. ✓ Creates Git tag (triggers GitHub Actions!)"
        echo "  7. ✓ Shows what happens next"
        exit 0
    fi

    local new_version="$1"

    print_header "WC SIMPLE FILTER - VERSION BUMP & RELEASE"

    # Step 1: Validate
    print_step "Validating version format..."
    validate_version_format "$new_version"
    print_success "Version format valid: $new_version (semantic versioning)"

    # Step 2: Check files exist
    print_step "Checking required files..."
    validate_files_exist
    print_success "All required files found"

    # Step 3: Validate Git status
    print_step "Checking Git status..."
    validate_git_status
    print_success "Git repository is clean"

    # Step 4: Update versions
    print_header "UPDATING VERSION NUMBERS"
    update_version_in_files "$new_version"

    # Step 5: Show deployment preview
    show_deployment_preview

    # Step 6: Confirmation
    echo ""
    if ! confirm "Proceed with commit, push, and tag creation?"; then
        print_error "Aborted by user"
        echo "To undo local changes: git checkout -- $PLUGIN_FILE $README_FILE"
        exit 1
    fi

    # Step 7: Commit and push
    commit_and_push "$new_version"

    # Step 8: Create and push tag
    create_and_push_tag "$new_version"

    # Step 9: Show next steps
    show_what_happens_next "$new_version"
}

# Run main function
main "$@"
