## 1. Implementation

- [x] 1.1 Add `add_action_links` callback method to `AdminPage` that prepends "Settings" and "Guide" links to the action links array
- [x] 1.2 Register the `plugin_action_links_{basename}` filter in `AdminPage::register()` using `VCIP_PLUGIN_FILE`
- [x] 2.3 Check in the code changes, as a separate commit from writing tests

## 2. Testing

- [x] 2.1 Add integration test verifying the filter callback returns "Settings" and "Guide" links prepended before existing links
- [x] 2.2 Add integration test verifying link URLs point to the correct settings page and user guide tab
- [x] 2.3 Add integration test verifying link labels are wrapped in translation functions

## 3. Quality

- [x] 3.1 Run `composer run-script check` (format, analyse, test) and fix any issues
- [x] 3.2 When complete and working, check in the code changes, ready to push the branch
