import { registerBlockType } from '@wordpress/blocks';
import { createElement, Fragment } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, RangeControl } from '@wordpress/components';

registerBlockType('prompt-manager/analytics-summary', {
  title: __('Analytics Summary', 'prompt-manager'),
  icon: 'chart-bar',
  category: 'prompt-manager',
  attributes: {
    days: { type: 'number', default: 30 },
  },
  edit: ({ attributes, setAttributes }) => {
    return createElement(
      Fragment,
      {},
      createElement('div', useBlockProps(), __('Analytics Summary', 'prompt-manager')),
      createElement(
        InspectorControls,
        {},
        createElement(
          PanelBody,
          { title: __('Settings', 'prompt-manager') },
          createElement(RangeControl, {
            label: __('Days', 'prompt-manager'),
            value: attributes.days,
            onChange: (value) => setAttributes({ days: value }),
            min: 1,
            max: 90,
          })
        )
      )
    );
  },
  save: () => null,
});
