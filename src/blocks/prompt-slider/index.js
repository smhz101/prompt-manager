import { registerBlockType } from '@wordpress/blocks';
import { createElement, Fragment } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, ToggleControl, RangeControl } from '@wordpress/components';

registerBlockType('prompt-manager/prompt-slider', {
  title: __('Prompt Slider', 'prompt-manager'),
  icon: 'images-alt2',
  category: 'prompt-manager',
  attributes: {
    numberOfPosts: { type: 'number', default: 5 },
    showNSFW: { type: 'boolean', default: false },
  },
  edit: ({ attributes, setAttributes }) => {
    return createElement(
      Fragment,
      {},
      createElement('div', useBlockProps(), __('Prompt Slider', 'prompt-manager')),
      createElement(
        InspectorControls,
        {},
        createElement(
          PanelBody,
          { title: __('Slider Settings', 'prompt-manager') },
          createElement(RangeControl, {
            label: __('Number of Prompts', 'prompt-manager'),
            value: attributes.numberOfPosts,
            onChange: (value) => setAttributes({ numberOfPosts: value }),
            min: 1,
            max: 20,
          }),
          createElement(ToggleControl, {
            label: __('Show NSFW', 'prompt-manager'),
            checked: attributes.showNSFW,
            onChange: (value) => setAttributes({ showNSFW: value }),
          })
        )
      )
    );
  },
  save: () => null,
});
