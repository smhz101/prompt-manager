import { registerBlockType } from '@wordpress/blocks';
import { createElement, Fragment } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
  InspectorControls,
  useBlockProps,
  InnerBlocks,
} from '@wordpress/block-editor';
import { PanelBody, SelectControl, RangeControl } from '@wordpress/components';

registerBlockType('prompt-manager/advance-query', {
  title: __('Advance Query', 'prompt-manager'),
  icon: 'filter',
  category: 'prompt-manager',
  attributes: {
    postsPerPage: { type: 'number', default: 5 },
    orderBy: { type: 'string', default: 'date' },
    order: { type: 'string', default: 'DESC' },
    category: { type: 'string', default: '' },
  },
  edit: ({ attributes, setAttributes }) => {
    const blockProps = useBlockProps();
    return createElement(
      Fragment,
      {},
      createElement(
        'div',
        blockProps,
        createElement(InnerBlocks, {
          orientation: 'vertical',
          template: [
            ['core/post-featured-image'],
            ['core/post-title'],
            ['core/post-excerpt'],
          ],
          templateLock: false,
        })
      ),
      createElement(
        InspectorControls,
        {},
        createElement(
          PanelBody,
          { title: __('Query Settings', 'prompt-manager') },
          createElement(RangeControl, {
            label: __('Posts Per Page', 'prompt-manager'),
            value: attributes.postsPerPage,
            onChange: (value) => setAttributes({ postsPerPage: value }),
            min: 1,
            max: 20,
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
          }),
          createElement(SelectControl, {
            label: __('Category', 'prompt-manager'),
            value: attributes.category,
            options: [
              { value: '', label: __('All', 'prompt-manager') },
              ...(promptManagerBlocks.categories || []),
            ],
            onChange: (value) => setAttributes({ category: value }),
          })
        )
      )
    );
  },
  save: () => null,
});
