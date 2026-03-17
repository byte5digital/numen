import { defineBuildConfig } from 'unbuild'

export default defineBuildConfig({
  entries: [
    { input: './src/index', name: 'index' },
    { input: './src/react/index', name: 'react/index' },
    { input: './src/vue/index', name: 'vue/index' },
    { input: './src/svelte/index', name: 'svelte/index' },
  ],
  declaration: true,
  rollup: {
    emitCJS: false,
    inlineDependencies: false,
  },
  failOnWarn: false,
})
