Given(/^I am on the page$/) do
  visit(ArticlePage, using_params: { article_name: "RevisionSlider-#{@random_string}" })
end

Given(/^I am on the diff page$/) do
  visit(ArticlePage, using_params: { article_name: "RevisionSlider-#{@random_string}", query: 'type=revision&diff=' })
end

Given(/^a page with (\d+) revision\(s\) exists$/) do |number_of_revisions|
  (1..number_of_revisions.to_i).each do |i|
    api.edit(title: "RevisionSlider-#{@random_string}", text: "RS-Text-#{i}", summary: "RS-Summary-#{i}")
  end
end

Given(/^I refresh the page$/) do
  on(ArticlePage) do |page|
    page.refresh
  end
end

When(/^I click the browser back button$/) do
  on(ArticlePage).back
end

When(/^I click the browser forward button$/) do
  on(ArticlePage).forward
end

When(/^I wait until the RevisionSlider has loaded$/) do
  step 'The RevisionSlider has loaded'
end

When(/^I have loaded the RevisionSlider and dismissed the help dialog$/) do
  step 'I click on the expand button'
  step 'I wait until the RevisionSlider has loaded'
  step 'I have dismissed the help dialog'
end

Then(/^The RevisionSlider has loaded$/) do
  on(DiffPage).wait_for_slider_to_load
end

Given(/^The window size is (\d+) by (\d+)$/) do |width, height|
  browser.window.resize_to(width.to_i, height.to_i)
end

Then(/^The auto expand button should be visible/) do
  expect(on(DiffPage).revisionslider_auto_expand_button_element.when_present).to be_visible
end

Given(/^I wait for the setting to be saved$/) do
  on(DiffPage).wait_for_ajax_calls
end

Then(/^The auto expand button should be off/) do
  expect(on(DiffPage).revisionslider_auto_expand_button_element.class_name).to match 'oo-ui-toggleWidget-off'
end

Then(/^The auto expand button should be on/) do
  expect(on(DiffPage).revisionslider_auto_expand_button_element.class_name).to match 'oo-ui-toggleWidget-on'
end

Then(/^There should be a RevisionSlider expand button/) do
  expect(on(DiffPage).revisionslider_toggle_button_element.when_present).to be_visible
end

Then(/^There should not be a RevisionSlider expand button/) do
  expect(on(DiffPage).revisionslider_toggle_button_element.when_not_present).not_to be_present
end

Then(/^RevisionSlider wrapper should be hidden/) do
  expect(on(DiffPage).revisionslider_wrapper_element.when_not_visible).not_to be_visible
end

Then(/^RevisionSlider wrapper should be visible/) do
  expect(on(DiffPage).revisionslider_wrapper_element.when_present).to be_visible
end

Given(/^I click on the auto expand button/) do
  on(DiffPage).revisionslider_auto_expand_button_element.when_visible.click
end

Given(/^I click on the expand button/) do
  on(DiffPage).revisionslider_toggle_button_element.when_visible.click
end
