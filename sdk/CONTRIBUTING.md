# Contributing to @numen/sdk

Thanks for wanting to contribute! Here's how to get started.

## Development Setup

```bash
# Clone the repo
git clone https://github.com/byte5digital/numen.git
cd numen/sdk

# Install dependencies
pnpm install

# Run tests in watch mode
pnpm --filter @numen/sdk dev

# Run tests once
pnpm --filter @numen/sdk test

# Build
pnpm --filter @numen/sdk build
```

### Prerequisites

- **Node.js** ≥ 18
- **pnpm** ≥ 8

## Project Structure

```
sdk/
├── packages/
│   └── sdk/
│       ├── src/
│       │   ├── core/        # Client, auth, cache, errors
│       │   ├── resources/   # API resource modules (16)
│       │   ├── react/       # React hooks & provider
│       │   ├── vue/         # Vue composables & plugin
│       │   ├── svelte/      # Svelte stores & context
│       │   ├── realtime/    # SSE client, polling, manager
│       │   └── types/       # Shared TypeScript types
│       └── tests/           # Vitest test suite
├── docs/                    # Documentation
└── pnpm-workspace.yaml
```

## Running Tests

```bash
# All tests
pnpm --filter @numen/sdk test

# Watch mode
pnpm --filter @numen/sdk dev

# Specific test file
pnpm --filter @numen/sdk test -- tests/core/client.test.ts
```

## Code Style

- TypeScript strict mode
- ES modules (`import`/`export`)
- Functional patterns preferred
- Descriptive names, minimal comments (code should be self-documenting)

## Pull Request Guidelines

1. **Branch from `dev`** — never from `main` directly
2. **One logical change per PR** — keep it focused
3. **All tests must pass** — run `pnpm --filter @numen/sdk test` before pushing
4. **TypeScript must compile** — no `// @ts-ignore` unless truly necessary
5. **Add tests for new features** — aim for the same coverage level
6. **Update docs** if your change affects the public API

## Commit Messages

Follow [Conventional Commits](https://www.conventionalcommits.org/):

```
feat(sdk): add new resource module
fix(sdk): handle edge case in pagination
docs(sdk): update React guide
test(sdk): add cache invalidation tests
```

## Reporting Issues

Open an issue on [GitHub](https://github.com/byte5digital/numen/issues) with:
- What you expected
- What happened
- Steps to reproduce
- SDK version and framework version

## License

By contributing, you agree that your contributions will be licensed under the MIT License.
