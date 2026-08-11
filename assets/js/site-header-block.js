/**
 * Editor-side registration for the rcmi/site-header and rcmi/site-footer
 * dynamic blocks.
 *
 * Both blocks are registered server-side with a render_callback (see
 * functions.php). This script registers them client-side with:
 *  - useBlockProps wrapper (required for apiVersion 3 so the block is
 *    selectable/clickable in the editor canvas)
 *  - InspectorControls with color pickers, style toggles, and logo text fields
 *  - ServerSideRender preview that receives the current attributes
 *
 * The actual menus are managed via Appearance > Menus.
 */
(function (wp) {
  'use strict';
  var el = wp.element.createElement;
  var ServerSideRender = wp.serverSideRender
    ? (wp.serverSideRender.ServerSideRender || wp.serverSideRender)
    : wp.components.ServerSideRender;
  var InspectorControls = wp.blockEditor.InspectorControls;
  var useBlockProps = wp.blockEditor.useBlockProps;
  var MediaUpload = wp.blockEditor.MediaUpload;
  var PanelBody = wp.components.PanelBody;
  var ColorPalette = wp.components.ColorPalette;
  var Dropdown = wp.components.Dropdown;
  var ToggleControl = wp.components.ToggleControl;
  var TextControl = wp.components.TextControl;
  var SelectControl = wp.components.SelectControl;
  var RangeControl = wp.components.RangeControl;
  var Button = wp.components.Button;
  var __ = wp.i18n.__;

  // UH brand color palette (matches theme.json).
  var UH_COLORS = [
    { name: 'White',           color: '#FFFFFF', slug: 'uh-white' },
    { name: 'Black',           color: '#000000', slug: 'uh-black' },
    { name: 'UH Red (Primary)',color: '#C8102E', slug: 'uh-red' },
    { name: 'Slate',           color: '#54585A', slug: 'uh-slate' },
    { name: 'Brick',           color: '#960C22', slug: 'uh-brick' },
    { name: 'Chocolate',       color: '#640817', slug: 'uh-chocolate' },
    { name: 'Cream',           color: '#FFF9D9', slug: 'uh-cream' },
    { name: 'Gray',            color: '#888B8D', slug: 'uh-gray' },
    { name: 'Gold',            color: '#F6BE00', slug: 'uh-gold' },
    { name: 'Mustard',         color: '#D89B00', slug: 'uh-mustard' },
    { name: 'Ocher',           color: '#B97800', slug: 'uh-ocher' },
    { name: 'Teal',            color: '#00B388', slug: 'uh-teal' },
    { name: 'Green',           color: '#00866C', slug: 'uh-green' },
    { name: 'Forest',          color: '#005950', slug: 'uh-forest' },
    { name: 'Background Alt',  color: '#F4F5F5', slug: 'bg-alt' },
    { name: 'Background Dark', color: '#101112', slug: 'bg-dark' },
    { name: 'Border',          color: '#DEE1E2', slug: 'border' }
  ];

  // ============================================================
  // Compact color selector (same pattern as rcmi-toolkit).
  // ============================================================
  function renderColorSelector(label, value, onChange) {
    return el('div', { style: { marginBottom: '12px' } },
      el('label', { style: { display: 'block', fontWeight: '600', marginBottom: '4px', fontSize: '11px' } }, label),
      el(Dropdown, {
        renderToggle: function (ref) {
          return el(Button, {
            onClick: ref.onToggle,
            'aria-expanded': ref.isOpen,
            variant: 'secondary',
            style: { width: '100%', justifyContent: 'flex-start', padding: '4px 8px', height: '28px' }
          },
            el('span', {
              style: {
                display: 'inline-block', width: '16px', height: '16px',
                borderRadius: '50%', marginRight: '8px',
                background: value || 'transparent',
                border: value ? '1px solid #ccc' : '1px dashed #ccc',
                verticalAlign: 'middle'
              }
            }),
            el('span', { style: { fontSize: '12px', verticalAlign: 'middle' } },
              value ? value : __('Select color', 'rcmi')
            )
          );
        },
        renderContent: function () {
          return el('div', { style: { padding: '8px', width: '220px' } },
            el(ColorPalette, {
              value: value,
              colors: UH_COLORS,
              onChange: function (color) { onChange(color || ''); },
              disableCustomColors: false,
              clearable: true
            })
          );
        }
      })
    );
  }

  // ============================================================
  // Image picker for the logo (optional — falls back to text logo).
  // ============================================================
  function renderImagePicker(label, valueId, valueUrl, onChange) {
    return el('div', { style: { marginBottom: '12px' } },
      el('label', { style: { display: 'block', fontWeight: '600', marginBottom: '4px', fontSize: '11px' } }, label),
      el(MediaUpload, {
        onSelect: function (media) {
          onChange({ logoImageId: media.id, logoImageUrl: media.url });
        },
        allowedTypes: ['image'],
        value: valueId,
        render: function (obj) {
          if (valueUrl) {
            return el('div', { style: { display: 'flex', alignItems: 'center', gap: '8px' } },
              el('img', { src: valueUrl, style: { maxWidth: '60px', maxHeight: '40px', borderRadius: '2px' } }),
              el(Button, { onClick: obj.open, variant: 'secondary', style: { fontSize: '11px' } }, __('Replace', 'rcmi')),
              el(Button, {
                onClick: function () { onChange({ logoImageId: 0, logoImageUrl: '' }); },
                variant: 'secondary', isDestructive: true, style: { fontSize: '11px' }
              }, __('Remove', 'rcmi'))
            );
          }
          return el(Button, { onClick: obj.open, variant: 'secondary', style: { width: '100%', justifyContent: 'center' } }, __('Choose image', 'rcmi'));
        }
      })
    );
  }

  // ============================================================
  // Buttons repeater — lets the editor add/remove/edit CTA buttons
  // that render into the .nav-cta container.
  // ============================================================
  function renderButtonsRepeater(buttons, setAttributes) {
    buttons = buttons || [];
    function updateBtn(idx, key, val) {
      var next = buttons.slice();
      next[idx] = Object.assign({}, next[idx]);
      next[idx][key] = val;
      setAttributes({ buttons: next });
    }
    function removeBtn(idx) {
      var next = buttons.slice();
      next.splice(idx, 1);
      setAttributes({ buttons: next });
    }
    function addBtn() {
      var next = buttons.slice();
      next.push({ text: '', link: '/', style: 'primary', borderRadius: 0 });
      setAttributes({ buttons: next });
    }
    function moveBtn(idx, dir) {
      var target = idx + dir;
      if (target < 0 || target >= buttons.length) return;
      var next = buttons.slice();
      var tmp = next[idx]; next[idx] = next[target]; next[target] = tmp;
      setAttributes({ buttons: next });
    }

    var rows = buttons.map(function (btn, idx) {
      return el('div', {
        key: 'btn-' + idx,
        style: { padding: '10px', marginBottom: '8px', border: '1px solid #e0e0e0', borderRadius: '4px', background: '#fafafa' }
      },
        el('div', { style: { display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '6px' } },
          el('strong', { style: { fontSize: '11px' } }, __('Button ' + (idx + 1), 'rcmi')),
          el('div', { style: { display: 'flex', gap: '2px' } },
            el(Button, {
              icon: 'arrow-up-alt2',
              label: __('Move up', 'rcmi'),
              isSmall: true, isTertiary: true,
              disabled: idx === 0,
              onClick: function () { moveBtn(idx, -1); }
            }),
            el(Button, {
              icon: 'arrow-down-alt2',
              label: __('Move down', 'rcmi'),
              isSmall: true, isTertiary: true,
              disabled: idx === buttons.length - 1,
              onClick: function () { moveBtn(idx, 1); }
            }),
            el(Button, {
              icon: 'trash',
              label: __('Remove', 'rcmi'),
              isSmall: true, isTertiary: true, isDestructive: true,
              onClick: function () { removeBtn(idx); }
            })
          )
        ),
        el(TextControl, {
          label: __('Text', 'rcmi'),
          value: btn.text || '',
          onChange: function (val) { updateBtn(idx, 'text', val); }
        }),
        el(TextControl, {
          label: __('Link', 'rcmi'),
          help: __('Relative (/about/) or full URL (https://...)', 'rcmi'),
          value: btn.link || '',
          onChange: function (val) { updateBtn(idx, 'link', val); }
        }),
        el(SelectControl, {
          label: __('Style', 'rcmi'),
          value: btn.style || 'primary',
          options: [
            { label: __('Primary (solid)', 'rcmi'), value: 'primary' },
            { label: __('Outline', 'rcmi'), value: 'outline' }
          ],
          onChange: function (val) { updateBtn(idx, 'style', val); }
        }),
        el(RangeControl, {
          label: __('Border radius (px)', 'rcmi'),
          help: __('Leave at 0 to use the theme default.', 'rcmi'),
          value: btn.borderRadius !== undefined && btn.borderRadius !== '' ? btn.borderRadius : 0,
          min: 0,
          max: 999,
          initialPosition: 0,
          onChange: function (val) { updateBtn(idx, 'borderRadius', val); }
        })
      );
    });

    rows.push(el(Button, {
      key: 'add-btn',
      icon: 'plus',
      variant: 'secondary',
      isSmall: true,
      onClick: addBtn,
      style: { width: '100%', justifyContent: 'center', marginTop: '4px' }
    }, __('Add button', 'rcmi')));

    return el('div', null, rows);
  }

  // ============================================================
  // Helper: build the edit function for a dynamic block with
  // InspectorControls and a useBlockProps-wrapped ServerSideRender.
  // ============================================================
  function makeEdit(blockName, panels) {
    return function (props) {
      var attrs = props.attributes;
      var setAttributes = props.setAttributes;
      var blockProps = useBlockProps({ className: 'rcmi-dynamic-block-editor' });

      // Build the sidebar panel elements.
      var panelEls = panels.map(function (panel) {
        var children = panel.controls.map(function (ctrl) {
          if (ctrl.type === 'color') {
            return renderColorSelector(ctrl.label, attrs[ctrl.attr], function (val) {
              var u = {}; u[ctrl.attr] = val; setAttributes(u);
            });
          }
          if (ctrl.type === 'toggle') {
            return el(ToggleControl, {
              label: ctrl.label,
              help: ctrl.help,
              checked: attrs[ctrl.attr],
              onChange: function (val) { var u = {}; u[ctrl.attr] = val; setAttributes(u); }
            });
          }
          if (ctrl.type === 'text') {
            return el(TextControl, {
              label: ctrl.label,
              help: ctrl.help,
              value: attrs[ctrl.attr],
              onChange: function (val) { var u = {}; u[ctrl.attr] = val; setAttributes(u); }
            });
          }
          if (ctrl.type === 'image') {
            return renderImagePicker(ctrl.label, attrs[ctrl.attr], attrs[ctrl.urlAttr], function (vals) { setAttributes(vals); });
          }
          if (ctrl.type === 'buttons') {
            return renderButtonsRepeater(attrs[ctrl.attr], setAttributes);
          }
          return null;
        });
        return el(PanelBody, { title: panel.title, initialOpen: panel.open !== false }, children);
      });

      return [
        el(InspectorControls, null, panelEls),
        el('div', blockProps,
          el(ServerSideRender, { block: blockName, attributes: attrs })
        )
      ];
    };
  }

  // ============================================================
  // Register rcmi/site-header
  // ============================================================
  wp.blocks.registerBlockType('rcmi/site-header', {
    apiVersion: 3,
    title: 'RCMI Site Header',
    description: 'Site header with the primary navigation menu (managed via Appearance > Menus).',
    category: 'layout',
    icon: 'admin-header',
    attributes: {
      backgroundColor: { type: 'string', default: '' },
      textColor: { type: 'string', default: '' },
      accentColor: { type: 'string', default: '' },
      ctaBgColor: { type: 'string', default: '' },
      ctaTextColor: { type: 'string', default: '' },
      sticky: { type: 'boolean', default: true },
      transparent: { type: 'boolean', default: false },
      logoMark: { type: 'string', default: 'RC' },
      logoText: { type: 'string', default: 'RCMI' },
      logoSub: { type: 'string', default: 'Research Capacity & Mentoring Institute' },
      logoImageId: { type: 'number', default: 0 },
      logoImageUrl: { type: 'string', default: '' },
      buttons: {
        type: 'array',
        default: [
          { text: 'Request Support',  link: '/#start',              style: 'outline' },
          { text: 'Explore Research', link: '/cores/#investigator', style: 'primary' }
        ]
      }
    },
    supports: {
      anchor: false,
      customClassName: false,
      html: false,
      align: false
    },
    edit: makeEdit('rcmi/site-header', [
      {
        title: __('Logo', 'rcmi'), open: true,
        controls: [
          { type: 'image', label: __('Logo image (optional)', 'rcmi'), attr: 'logoImageId', urlAttr: 'logoImageUrl' },
          { type: 'text', label: __('Logo mark (text badge)', 'rcmi'), attr: 'logoMark' },
          { type: 'text', label: __('Logo text', 'rcmi'), attr: 'logoText' },
          { type: 'text', label: __('Logo subtitle', 'rcmi'), attr: 'logoSub' }
        ]
      },
      {
        title: __('Buttons', 'rcmi'), open: true,
        controls: [
          { type: 'buttons', attr: 'buttons' }
        ]
      },
      {
        title: __('Colors', 'rcmi'), open: true,
        controls: [
          { type: 'color', label: __('Background', 'rcmi'), attr: 'backgroundColor' },
          { type: 'color', label: __('Text / Links', 'rcmi'), attr: 'textColor' },
          { type: 'color', label: __('Accent / Hover', 'rcmi'), attr: 'accentColor' },
          { type: 'color', label: __('CTA Button Background', 'rcmi'), attr: 'ctaBgColor' },
          { type: 'color', label: __('CTA Button Text', 'rcmi'), attr: 'ctaTextColor' }
        ]
      },
      {
        title: __('Style', 'rcmi'), open: true,
        controls: [
          { type: 'toggle', label: __('Sticky header', 'rcmi'), help: __('Keep the header fixed at the top when scrolling.', 'rcmi'), attr: 'sticky' },
          { type: 'toggle', label: __('Transparent / blur', 'rcmi'), help: __('Semi-transparent background with a blur effect.', 'rcmi'), attr: 'transparent' }
        ]
      }
    ]),
    save: function () { return null; }
  });

  // ============================================================
  // Register rcmi/site-footer
  // ============================================================
  wp.blocks.registerBlockType('rcmi/site-footer', {
    apiVersion: 3,
    title: 'RCMI Site Footer',
    description: 'Site footer with the footer navigation menu (managed via Appearance > Menus).',
    category: 'layout',
    icon: 'admin-footer',
    attributes: {
      backgroundColor: { type: 'string', default: '' },
      textColor: { type: 'string', default: '' },
      accentColor: { type: 'string', default: '' },
      borderTop: { type: 'boolean', default: false },
      logoMark: { type: 'string', default: 'RC' },
      logoText: { type: 'string', default: 'RCMI' },
      footerText: { type: 'string', default: 'Research Capacity & Mentoring Institute — building research capacity, developing investigators, and partnering with communities to improve chronic disease outcomes.' },
      copyrightText: { type: 'string', default: '© {year} UH RCMI' }
    },
    supports: {
      anchor: false,
      customClassName: false,
      html: false,
      align: false
    },
    edit: makeEdit('rcmi/site-footer', [
      {
        title: __('Logo & Text', 'rcmi'), open: true,
        controls: [
          { type: 'text', label: __('Logo mark (text badge)', 'rcmi'), attr: 'logoMark' },
          { type: 'text', label: __('Logo text', 'rcmi'), attr: 'logoText' },
          { type: 'text', label: __('Footer description', 'rcmi'), attr: 'footerText' },
          { type: 'text', label: __('Copyright text', 'rcmi'), attr: 'copyrightText', help: __('Use {year} for the current year.', 'rcmi') }
        ]
      },
      {
        title: __('Colors', 'rcmi'), open: true,
        controls: [
          { type: 'color', label: __('Background', 'rcmi'), attr: 'backgroundColor' },
          { type: 'color', label: __('Text / Links', 'rcmi'), attr: 'textColor' },
          { type: 'color', label: __('Accent / Hover', 'rcmi'), attr: 'accentColor' }
        ]
      },
      {
        title: __('Style', 'rcmi'), open: true,
        controls: [
          { type: 'toggle', label: __('Top border', 'rcmi'), help: __('Add a colored border above the footer.', 'rcmi'), attr: 'borderTop' }
        ]
      }
    ]),
    save: function () { return null; }
  });
})(window.wp);
