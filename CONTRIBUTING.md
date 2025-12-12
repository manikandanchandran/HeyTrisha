# Contributing to Hey Trisha

Thank you for your interest in contributing to Hey Trisha! This document provides guidelines and instructions for contributing.

## 🤝 How to Contribute

### 1. Fork the Repository

First, fork the repository from [https://github.com/manikandanchandran/HeyTrisha](https://github.com/manikandanchandran/HeyTrisha) to your GitHub account.

### 2. Clone Your Fork

```bash
git clone https://github.com/YOUR_USERNAME/HeyTrisha.git
cd HeyTrisha
```

### 3. Create a Feature Branch

```bash
git checkout -b feature/your-feature-name
```

Use descriptive branch names:
- `feature/add-new-query-type`
- `bugfix/fix-error-handling`
- `docs/update-readme`

### 4. Make Your Changes

- Write clean, readable code
- Follow the coding standards (see below)
- Add comments for complex logic
- Test your changes thoroughly

### 5. Commit Your Changes

```bash
git add .
git commit -m 'Add: Description of your changes'
```

**Commit Message Guidelines:**
- Use clear, descriptive messages
- Start with a verb: `Add:`, `Fix:`, `Update:`, `Remove:`
- Keep messages concise but informative
- Examples:
  - `Add: Name-based post editing with confirmation`
  - `Fix: 500 error in NLPController`
  - `Update: README with new configuration instructions`

### 6. Push to Your Branch

```bash
git push origin feature/your-feature-name
```

### 7. Open a Pull Request

1. Go to the original repository: [https://github.com/manikandanchandran/HeyTrisha](https://github.com/manikandanchandran/HeyTrisha)
2. Click "New Pull Request"
3. Select your fork and branch
4. Fill out the PR template
5. Submit the PR

## 📋 Coding Standards

### PHP (Laravel/WordPress)

- Follow **PSR-12** coding standards
- Use meaningful variable and function names
- Add PHPDoc comments for classes and methods
- Keep functions focused and single-purpose
- Use dependency injection where appropriate

**Example:**
```php
/**
 * Search for posts or products by name
 * 
 * @param string $name The name/title to search for
 * @param string $type 'post', 'product', or 'both'
 * @return array|null Returns array with 'id', 'title' or 'name', and 'type'
 */
public function searchByName($name, $type = 'both')
{
    // Implementation
}
```

### JavaScript/React

- Use modern ES6+ syntax
- Follow React best practices
- Use meaningful component and variable names
- Add comments for complex logic

### CSS

- Use consistent naming conventions
- Keep styles organized and modular
- Use CSS variables for theming
- Follow BEM methodology where appropriate

## 🚫 What NOT to Commit

- **Never commit sensitive data:**
  - API keys
  - Passwords
  - Database credentials
  - `.env` files

- **Never commit generated files:**
  - `vendor/` directory
  - `node_modules/`
  - Build artifacts
  - Cache files

- **Never commit hardcoded values:**
  - Site-specific URLs
  - Hardcoded credentials
  - Environment-specific paths

## ✅ Before Submitting

Checklist before submitting your PR:

- [ ] Code follows coding standards
- [ ] All tests pass (if applicable)
- [ ] Documentation is updated
- [ ] No hardcoded values
- [ ] Error handling is in place
- [ ] Code is commented where necessary
- [ ] No sensitive data is committed
- [ ] `.gitignore` is properly configured

## 🧪 Testing

Before submitting, please test:

1. **Installation**: Plugin installs and activates correctly
2. **Configuration**: All settings work through WordPress admin
3. **Functionality**: Core features work as expected
4. **Error Handling**: Errors are handled gracefully
5. **Cross-browser**: Works in major browsers
6. **Performance**: No significant performance regressions

## 📝 Documentation

When adding new features:

1. Update `README.md` with new features
2. Update `CHANGELOG.md` with your changes
3. Add inline code comments
4. Update configuration documentation if needed

## 🐛 Reporting Bugs

If you find a bug:

1. Check if it's already reported in Issues
2. Create a new issue with:
   - Clear description
   - Steps to reproduce
   - Expected vs. actual behavior
   - Environment details (PHP version, WordPress version, etc.)
   - Error messages/logs

## 💡 Suggesting Features

To suggest a new feature:

1. Check if it's already suggested
2. Create an issue with:
   - Clear description
   - Use case/benefit
   - Possible implementation approach (optional)

## 📞 Questions?

If you have questions about contributing:

- Open an issue for discussion
- Check existing issues and PRs
- Review the codebase for examples

## 🙏 Thank You!

Your contributions make this project better for everyone. Thank you for taking the time to contribute!

---

**Remember**: The goal is to make this plugin better for all users. Be respectful, constructive, and helpful in all interactions.

