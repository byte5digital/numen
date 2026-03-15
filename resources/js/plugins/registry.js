/**
 * PluginComponentRegistry — slot-based Vue component registry for the Numen plugin system.
 *
 * Supported slots:
 *   - admin.sidebar
 *   - admin.dashboard.widget
 *   - pipeline.stage.config
 *   - content.edit.sidebar
 */

export class PluginComponentRegistry {
    constructor() {
        /** @type {Map<string, Map<string, object>>} */
        this._slots = new Map();
    }

    /**
     * Register a Vue component for a given slot.
     *
     * @param {string} slot   – Slot identifier (e.g. 'admin.sidebar')
     * @param {string} name   – Unique name for this component within the slot
     * @param {object} component – Vue component definition / SFC
     */
    register(slot, name, component) {
        if (!this._slots.has(slot)) {
            this._slots.set(slot, new Map());
        }
        this._slots.get(slot).set(name, component);
    }

    /**
     * Resolve a single component by slot + name.
     *
     * @param {string} slot
     * @param {string} name
     * @returns {object|null}
     */
    resolve(slot, name) {
        return this._slots.get(slot)?.get(name) ?? null;
    }

    /**
     * Get all components registered for a slot (in insertion order).
     *
     * @param {string} slot
     * @returns {object[]}
     */
    getSlotComponents(slot) {
        return this._slots.has(slot)
            ? Array.from(this._slots.get(slot).values())
            : [];
    }

    /**
     * Clear all registrations for a slot (useful during hot-reload / testing).
     *
     * @param {string} slot
     */
    clearSlot(slot) {
        this._slots.delete(slot);
    }

    /**
     * List all registered slot names.
     *
     * @returns {string[]}
     */
    getRegisteredSlots() {
        return Array.from(this._slots.keys());
    }
}

// ─── Well-known slot constants ────────────────────────────────────────────────
export const PLUGIN_SLOTS = {
    ADMIN_SIDEBAR: 'admin.sidebar',
    ADMIN_DASHBOARD_WIDGET: 'admin.dashboard.widget',
    PIPELINE_STAGE_CONFIG: 'pipeline.stage.config',
    CONTENT_EDIT_SIDEBAR: 'content.edit.sidebar',
};

// ─── Singleton ────────────────────────────────────────────────────────────────
export const pluginRegistry = new PluginComponentRegistry();

export default pluginRegistry;
