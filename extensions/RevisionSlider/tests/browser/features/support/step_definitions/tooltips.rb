Given(/^I hover over revision (\d+)$/) do |index|
    on(DiffPage).revisionslider_rev(index.to_i).hover
  end

Given(/^I hover over the revision (\d+) tooltip$/) do |index|
  on(DiffPage).revisionslider_tooltip(index.to_i).hover
end

Then(/^a tooltip should be present for revision (\d+)$/) do |index|
  expect(on(DiffPage).revisionslider_tooltip(index.to_i).when_present).to be_visible
end

Then(/^no tooltip should be present for revision (\d+)$/) do |index|
  expect(on(DiffPage).revisionslider_tooltip(index.to_i).when_not_present).not_to be_present
end
