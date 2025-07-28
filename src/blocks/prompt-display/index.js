import { registerBlockType } from '@wordpress/blocks';
import { createElement, Fragment } from '@wordpress/element';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
  PanelBody,
  SelectControl,
  ToggleControl,
  RangeControl,
  TextControl,
  ColorPicker,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

registerBlockType('prompt-manager/prompt-display', {
  title: __('Prompt Display', 'prompt-manager'),
  icon: 'lightbulb',
  category: 'prompt-manager',
  attributes: {
    promptId: { type: 'number', default: 0 },
    showTitle: { type: 'boolean', default: true },
    showExcerpt: { type: 'boolean', default: true },
    showImage: { type: 'boolean', default: true },
    imageSize: { type: 'string', default: 'medium' },
    alignment: { type: 'string', default: 'none' },
  },
  edit: (props) => {
    const { attributes, setAttributes } = props;
    const blockProps = useBlockProps();
    return createElement(
      Fragment,
      {},
      createElement(
        'div',
        blockProps,
        createElement(
          'div',
          { className: 'prompt-manager-block-placeholder' },
          attributes.promptId
            ? createElement(
                'p',
                {},
                __('Prompt Display: ', 'prompt-manager') +
                  (promptManagerBlocks.prompts.find((p) => p.value === attributes.promptId)?.label ||
                    __('Unknown Prompt', 'prompt-manager'))
              )
            : createElement('p', {}, __('Select a prompt to display', 'prompt-manager'))
        )
      ),
      createElement(
        InspectorControls,
        {},
        createElement(
          PanelBody,
          { title: __('Prompt Settings', 'prompt-manager') },
          createElement(SelectControl, {
            label: __('Select Prompt', 'prompt-manager'),
            value: attributes.promptId,
            options: [{ value: 0, label: __('Select a prompt...', 'prompt-manager') }].concat(
              promptManagerBlocks.prompts
            ),
            onChange: (value) => setAttributes({ promptId: parseInt(value) }),
          }),
          createElement(ToggleControl, {
            label: __('Show Title', 'prompt-manager'),
            checked: attributes.showTitle,
            onChange: (value) => setAttributes({ showTitle: value }),
          }),
          createElement(ToggleControl, {
            label: __('Show Excerpt', 'prompt-manager'),
            checked: attributes.showExcerpt,
            onChange: (value) => setAttributes({ showExcerpt: value }),
          }),
          createElement(ToggleControl, {
            label: __('Show Image', 'prompt-manager'),
            checked: attributes.showImage,
            onChange: (value) => setAttributes({ showImage: value }),
          }),
          attributes.showImage &&
            createElement(SelectControl, {
              label: __('Image Size', 'prompt-manager'),
              value: attributes.imageSize,
              options: promptManagerBlocks.imageSizes,
              onChange: (value) => setAttributes({ imageSize: value }),
            }),
          createElement(SelectControl, {
            label: __('Alignment', 'prompt-manager'),
            value: attributes.alignment,
            options: [
              { value: 'none', label: __('None', 'prompt-manager') },
              { value: 'left', label: __('Left', 'prompt-manager') },
              { value: 'center', label: __('Center', 'prompt-manager') },
              { value: 'right', label: __('Right', 'prompt-manager') },
            ],
            onChange: (value) => setAttributes({ alignment: value }),
          })
        )
      )
    );
  },
  save: () => null,
});
