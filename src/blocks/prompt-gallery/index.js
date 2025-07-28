import { registerBlockType } from '@wordpress/blocks';
import { createElement, Fragment } from '@wordpress/element';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
  PanelBody,
  SelectControl,
  ToggleControl,
  RangeControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

registerBlockType('prompt-manager/prompt-gallery', {
  title: __('Prompt Gallery', 'prompt-manager'),
  icon: 'grid-view',
  category: 'prompt-manager',
  attributes: {
    numberOfPosts: { type: 'number', default: 6 },
    columns: { type: 'number', default: 3 },
    showNSFW: { type: 'boolean', default: false },
    orderBy: { type: 'string', default: 'date' },
    order: { type: 'string', default: 'DESC' },
    category: { type: 'string', default: '' },
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
          createElement('p', {}, __('Prompt Gallery', 'prompt-manager')),
          createElement(
            'small',
            {},
            __('Showing', 'prompt-manager') +
              ' ' +
              attributes.numberOfPosts +
              ' ' +
              __('prompts in', 'prompt-manager') +
              ' ' +
              attributes.columns +
              ' ' +
              __('columns', 'prompt-manager')
          )
        )
      ),
      createElement(
        InspectorControls,
        {},
        createElement(
          PanelBody,
          { title: __('Gallery Settings', 'prompt-manager') },
          createElement(RangeControl, {
            label: __('Number of Posts', 'prompt-manager'),
            value: attributes.numberOfPosts,
            onChange: (value) => setAttributes({ numberOfPosts: value }),
            min: 1,
            max: 20,
          }),
          createElement(RangeControl, {
            label: __('Columns', 'prompt-manager'),
            value: attributes.columns,
            onChange: (value) => setAttributes({ columns: value }),
            min: 1,
            max: 6,
          }),
          createElement(ToggleControl, {
            label: __('Show NSFW Content', 'prompt-manager'),
            checked: attributes.showNSFW,
            onChange: (value) => setAttributes({ showNSFW: value }),
          }),
          createElement(SelectControl, {
            label: __('Order By', 'prompt-manager'),
            value: attributes.orderBy,
            options: [
              { value: 'date', label: __('Date', 'prompt-manager') },
              { value: 'title', label: __('Title', 'prompt-manager') },
              { value: 'menu_order', label: __('Menu Order', 'prompt-manager') },
              { value: 'rand', label: __('Random', 'prompt-manager') },
            ],
            onChange: (value) => setAttributes({ orderBy: value }),
          }),
          createElement(SelectControl, {
            label: __('Order', 'prompt-manager'),
            value: attributes.order,
            options: [
              { value: 'DESC', label: __('Descending', 'prompt-manager') },
              { value: 'ASC', label: __('Ascending', 'prompt-manager') },
            ],
            onChange: (value) => setAttributes({ order: value }),
          })
        )
      )
    );
  },
  save: () => null,
});
