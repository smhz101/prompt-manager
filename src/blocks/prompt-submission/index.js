import { registerBlockType } from '@wordpress/blocks';
import { createElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';

registerBlockType('prompt-manager/prompt-submission', {
  title: __('Prompt Submission', 'prompt-manager'),
  icon: 'edit',
  category: 'prompt-manager',
  edit: () => createElement('div', useBlockProps(), __('Prompt Submission Form', 'prompt-manager')),
  save: () => null,
});
