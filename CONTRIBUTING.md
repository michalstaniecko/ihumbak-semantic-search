# Contributing to Ihumbak Semantic Search

Thank you for your interest in contributing to this project!

## Development Process

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Make your changes
4. Run tests and linting
5. Commit your changes (`git commit -m 'Add some amazing feature'`)
6. Push to the branch (`git push origin feature/amazing-feature`)
7. Open a Pull Request

## Coding Standards

This project follows WordPress Coding Standards. Please ensure your code passes all checks:

```bash
composer lint
composer stan
composer test
```

## Testing

All new features should include unit tests. Run tests with:

```bash
composer test
```

## Pull Request Guidelines

- Keep changes focused and atomic
- Write clear commit messages
- Update documentation as needed
- Ensure all tests pass
- Follow existing code style

## Branching Strategy

- `main` - stable releases
- `develop` - development branch
- `feature/*` - new features
- `bugfix/*` - bug fixes
- `release/*` - release preparation

## Questions?

Open an issue for any questions or discussions.
