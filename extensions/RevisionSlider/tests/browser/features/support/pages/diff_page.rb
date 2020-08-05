class DiffPage
  include PageObject

  link(:differences_prevlink, id: 'differences-prevlink')
  link(:differences_nextlink, id: 'differences-nextlink')

  p(:revisionslider_placeholder, css: '.mw-revslider-placeholder')
  div(:revisionslider_wrapper, css: '.mw-revslider-slider-wrapper')
  span(:revisionslider_auto_expand_button, css: '.mw-revslider-auto-expand-button')
  span(:revisionslider_toggle_button, css: '.mw-revslider-toggle-button')
  table(:revisionslider_loading, css: '.mw-revslider-diff-loading')

  div(:revisionslider_help_dialog, css: '.mw-revslider-help-dialog')
  button(:revisionslider_help, css: '.mw-revision-slider-container > button')
  link(:revisionslider_help_next, css: '.mw-revslider-help-next > a')
  link(:revisionslider_help_previous, css: '.mw-revslider-help-previous > a')
  link(:revisionslider_help_close_start, css: '.mw-revslider-help-close-start > a')
  link(:revisionslider_help_close_end, css: '.mw-revslider-help-close-end > a')

  span(:revisionslider_timeline_backwards, css: '.mw-revslider-arrow.mw-revslider-arrow-backwards:not(.oo-ui-widget-disabled)')
  span(:revisionslider_timeline_forwards, css: '.mw-revslider-arrow.mw-revslider-arrow-forwards:not(.oo-ui-widget-disabled)')

  span(:revisionslider_timeline_backwards_disabled, css: '.mw-revslider-arrow.mw-revslider-arrow-backwards.oo-ui-widget-disabled')
  span(:revisionslider_timeline_forwards_disabled, css: '.mw-revslider-arrow.mw-revslider-arrow-forwards.oo-ui-widget-disabled')

  div(:revisionslider_pointer_older, css: '.mw-revslider-pointer-older')
  div(:revisionslider_pointer_newer, css: '.mw-revslider-pointer-newer')

  div(:revisionslider_left_summary, id: 'mw-diff-otitle3')
  div(:revisionslider_right_summary, id: 'mw-diff-ntitle3')

  def revisionslider_rev(index = 1)
    element('div', css: '.mw-revslider-revision[data-pos="' + index.to_s + '"]')
  end

  def click_revision_older(index = 1)
    revbar = revisionslider_rev(index).element.wd
    browser.driver.action.move_to(revbar, 1, revbar.size.height - 1).click.perform
  end

  def click_revision_newer(index = 1)
    revbar = revisionslider_rev(index).element.wd
    browser.driver.action.move_to(revbar, 1, 0).click.perform
  end

  def click_older_edit_link
    differences_prevlink_element.when_visible.click
  end

  def click_newer_edit_link
    differences_nextlink_element.when_visible.click
  end

  def revisionslider_tooltip(index = 1)
    element('div', css: '.mw-revslider-revision-tooltip-' + index.to_s)
  end

  def wait_for_slider_to_load
    wait_until do
      !revisionslider_placeholder?
    end
  end

  def wait_for_ajax_calls
    sleep_period = 0.25
    max_timeout_seconds = 2
    timeout_loops = (max_timeout_seconds / sleep_period).to_i

    while execute_script('return jQuery.active') != 0 && timeout_loops > 0
      sleep(sleep_period)
      timeout_loops -= 1
    end
    true
  end

  def wait_for_animations
    sleep_period = 0.25
    max_timeout_seconds = 2
    timeout_loops = (max_timeout_seconds / sleep_period).to_i

    while execute_script('return $(\':animated\').length') != 0 && timeout_loops > 0
      sleep(sleep_period)
      timeout_loops -= 1
    end
    true
  end

  def wait_for_diff_to_load
    wait_until do
      !revisionslider_loading?
    end
  end

  def wait_for_help_dialog_to_hide
    wait_until do
      !revisionslider_help_dialog_element.visible?
    end
  end
end
