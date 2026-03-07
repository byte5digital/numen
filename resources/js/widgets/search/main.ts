import { defineCustomElement } from 'vue'
import SearchWidgetCE from './SearchWidget.ce.vue'

// Register as Web Component
const NumenSearch = defineCustomElement(SearchWidgetCE)
customElements.define('numen-search', NumenSearch)

export { NumenSearch }
