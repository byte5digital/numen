#!/usr/bin/env node
import yargs from 'yargs'
import { hideBin } from 'yargs/helpers'
import { generate } from './generate.js'

const argv = await yargs(hideBin(process.argv))
  .usage('Usage: $0 [options]')
  .option('input', {
    alias: 'i',
    type: 'string',
    description: 'Path to OpenAPI spec file (YAML or JSON)',
    default: '../openapi.yaml',
  })
  .option('output', {
    alias: 'o',
    type: 'string',
    description: 'Output path for generated TypeScript types',
    default: '../sdk/src/generated/api.ts',
  })
  .help()
  .alias('help', 'h')
  .parseAsync()

try {
  await generate({
    input: argv.input,
    output: argv.output,
  })
} catch (err) {
  console.error((err as Error).message)
  process.exit(1)
}
