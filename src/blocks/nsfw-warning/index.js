import { registerBlockType } from '@wordpress/blocks';
import { createElement, Fragment } from '@wordpress/element';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, TextControl, ColorPicker } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

registerBlockType('prompt-manager/nsfw-warning', {
  title: __('NSFW Warning', 'prompt-manager'),
  icon: 'warning',
  category: 'prompt-manager',
  attributes: {
    warningText: {
      type: 'string',
      default: __('This content contains NSFW material. You must be logged in to view it.', 'prompt-manager'),
    },
    buttonText: { type: 'string', default: __('Login to View', 'prompt-manager') },
    backgroundColor: { type: 'string', default: '#fef2f2' },
    textColor: { type: 'string', default: '#dc2626' },
  },
  edit: (props) => {
    const { attributes, setAttributes } = props;
    const blockProps = useBlockProps({
      style: {
        backgroundColor: attributes.backgroundColor,
        color: attributes.textColor,
        padding: '20px',
        borderRadius: '8px',
        textAlign: 'center',
      },
    });
    return createElement(
      Fragment,
      {},
      createElement(
        'div',
        blockProps,
        createElement('p', {}, attributes.warningText),
        createElement(
          'button',
          {
            className: 'button',
            style: {
              backgroundColor: attributes.textColor,
              color: attributes.backgroundColor,
              border: 'none',
              padding: '10px 20px',
              borderRadius: '4px',
            },
          },
          attributes.buttonText
        )
      ),
      createElement(
        InspectorControls,
        {},
        createElement(
          PanelBody,
          { title: __('Warning Settings', 'prompt-manager') },
          createElement(TextControl, {
            label: __('Warning Text', 'prompt-manager'),
            value: attributes.warningText,
            onChange: (value) => setAttributes({ warningText: value }),
          }),
          createElement(TextControl, {
            label: __('Button Text', 'prompt-manager'),
            value: attributes.buttonText,
            onChange: (value) => setAttributes({ buttonText: value }),
          }),
          createElement(
            'div',
            { style: { marginBottom: '20px' } },
            createElement('label', {}, __('Background Color', 'prompt-manager')),
            createElement(ColorPicker, {
              color: attributes.backgroundColor,
              onChangeComplete: (color) => setAttributes({ backgroundColor: color.hex }),
            })
          ),
          createElement(
            'div',
            { style: { marginBottom: '20px' } },
            createElement('label', {}, __('Text Color', 'prompt-manager')),
            createElement(ColorPicker, {
              color: attributes.textColor,
              onChangeComplete: (color) => setAttributes({ textColor: color.hex }),
            })
          )
        )
      )
    );
  },
  save: () => null,
});
