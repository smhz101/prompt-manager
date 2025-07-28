jQuery(document).ready(($) => {
  // Handle watermark toggle buttons
  $('.watermark-toggle-btn').on('click', function () {
    var $button = $(this);
    var postId = $button.data('post-id');
    var enabled = $button.data('enabled') === '1';
    var $status = $('#status-' + postId);

    $button.prop('disabled', true);
    $status.text('Processing...');

    $.ajax({
      url: promptManager.ajaxurl,
      type: 'POST',
      data: {
        action: 'toggle_watermark',
        post_id: postId,
        enable: enabled ? '0' : '1',
        nonce: promptManager.nonces.watermark,
      },
      success: (response) => {
        if (response.success) {
          $status.text('✅ Updated!').css('color', 'green');
          setTimeout(() => {
            location.reload();
          }, 1500);
        } else {
          $status.text('❌ Failed: ' + response.data).css('color', 'red');
        }
      },
      error: () => {
        $status.text('❌ Error').css('color', 'red');
      },
      complete: () => {
        $button.prop('disabled', false);
      },
    });
  });

  // Handle reblur buttons
  $('.reblur-btn').on('click', function () {
    var $button = $(this);
    var postId = $button.data('post-id');
    var $status = $('#status-' + postId);

    $button.prop('disabled', true);
    $status.text('Processing...');

    $.ajax({
      url: promptManager.ajaxurl,
      type: 'POST',
      data: {
        action: 'reblur_prompt',
        post_id: postId,
        nonce: promptManager.nonces.reblur,
      },
      success: (response) => {
        if (response.success) {
          $status.text('✅ Success!').css('color', 'green');
          setTimeout(() => {
            location.reload();
          }, 1500);
        } else {
          $status.text('❌ Failed: ' + response.data).css('color', 'red');
        }
      },
      error: () => {
        $status.text('❌ Error').css('color', 'red');
      },
      complete: () => {
        $button.prop('disabled', false);
      },
    });
  });

  // Handle bulk actions
  $('#apply-bulk-action').on('click', function () {
    var action = $('#bulk-nsfw-action').val();
    var selectedPosts = $('.prompt-checkbox:checked')
      .map(function () {
        return this.value;
      })
      .get();

    if (!action || selectedPosts.length === 0) {
      alert('Please select an action and at least one prompt.');
      return;
    }

    if (
      confirm('Are you sure you want to apply this action to ' + selectedPosts.length + ' prompts?')
    ) {
      var $button = $(this);
      $button.prop('disabled', true).text('Processing...');

      $.ajax({
        url: promptManager.ajaxurl,
        type: 'POST',
        data: {
          action: 'bulk_nsfw_action',
          bulk_action: action,
          post_ids: selectedPosts,
          nonce: promptManager.nonces.bulk,
        },
        success: (response) => {
          if (response.success) {
            alert('Bulk action completed successfully!');
            location.reload();
          } else {
            alert('Bulk action failed: ' + response.data);
          }
        },
        error: () => {
          alert('Bulk action failed due to an error.');
        },
        complete: () => {
          $button.prop('disabled', false).text('Apply');
        },
      });
    }
  });

  // Select all functionality
  $('#select-all-prompts').on('change', function () {
    $('.prompt-checkbox').prop('checked', this.checked);
  });
});
