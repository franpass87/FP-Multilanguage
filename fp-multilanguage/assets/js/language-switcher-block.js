(function (wp) {
    'use strict';

    if (!wp || !wp.blocks || !wp.element) {
        return;
    }

    var registerBlockType = wp.blocks.registerBlockType;
    var __ = wp.i18n && wp.i18n.__ ? wp.i18n.__ : function (text) { return text; };
    var el = wp.element.createElement;
    var blockEditor = wp.blockEditor || wp.editor || {};
    var InspectorControls = blockEditor.InspectorControls || function () { return null; };
    var PanelBody = wp.components && wp.components.PanelBody ? wp.components.PanelBody : function (props) { return el('div', props, props.children); };
    var SelectControl = wp.components && wp.components.SelectControl ? wp.components.SelectControl : function () { return null; };
    var ServerSideRender = wp.serverSideRender || null;

    if (!registerBlockType) {
        return;
    }

    registerBlockType('fp-multilanguage/language-switcher', {
        title: __('Selettore lingua', 'fp-multilanguage'),
        description: __('Aggiunge lo switcher di lingua del plugin direttamente all\'editor a blocchi.', 'fp-multilanguage'),
        icon: 'translation',
        category: 'widgets',
        attributes: {
            layout: {
                type: 'string',
                default: 'list'
            }
        },
        supports: {
            html: false
        },
        edit: function (props) {
            var layout = props.attributes.layout || 'list';

            function onChangeLayout(value) {
                props.setAttributes({ layout: value || 'list' });
            }

            var controls = el(
                InspectorControls,
                {},
                el(
                    PanelBody,
                    { title: __('Opzioni switcher', 'fp-multilanguage'), initialOpen: true },
                    el(SelectControl, {
                        label: __('Layout', 'fp-multilanguage'),
                        value: layout,
                        options: [
                            { label: __('Elenco verticale', 'fp-multilanguage'), value: 'list' },
                            { label: __('In linea', 'fp-multilanguage'), value: 'inline' }
                        ],
                        onChange: onChangeLayout
                    })
                )
            );

            if (ServerSideRender) {
                var preview = el(ServerSideRender, {
                    block: 'fp-multilanguage/language-switcher',
                    attributes: props.attributes
                });

                return [controls, preview];
            }

            return [
                controls,
                el(
                    'p',
                    { className: 'fp-multilanguage-block-placeholder' },
                    __('Anteprima non disponibile in questa versione di WordPress.', 'fp-multilanguage')
                )
            ];
        },
        save: function () {
            return null;
        }
    });
})(window.wp);
