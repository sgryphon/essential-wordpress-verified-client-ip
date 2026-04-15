## ADDED Requirements

### Requirement: vcip_resolved_ip filter is applied with the resolved IP
The system SHALL call `apply_filters('vcip_resolved_ip', $resolved_ip)` after resolving the client IP, passing the resolved IP as the first argument.

#### Scenario: vcip_resolved_ip filter is called with the resolved IP
- **WHEN** the plugin boots and REMOTE_ADDR is replaced with a new IP
- **THEN** the `vcip_resolved_ip` filter SHALL have been applied with the resolved IP as its first argument

### Requirement: vcip_trusted_proxies filter is applied during resolution
The system SHALL call `apply_filters('vcip_trusted_proxies', ...)` during IP resolution to allow dynamic proxy list modification.

#### Scenario: vcip_trusted_proxies filter is called during boot
- **WHEN** the plugin boots
- **THEN** the `vcip_trusted_proxies` filter SHALL have been applied

### Requirement: vcip_ip_resolved action is fired with new IP, original IP, and step trace when IP changes
The system SHALL call `do_action('vcip_ip_resolved', $new_ip, $original_ip, $steps)` after a successful IP replacement.

#### Scenario: vcip_ip_resolved action fires with correct arguments
- **WHEN** the plugin boots and REMOTE_ADDR is replaced
- **THEN** the `vcip_ip_resolved` action SHALL have been fired with the resolved IP as argument 0, the original REMOTE_ADDR as argument 1, and an array as argument 2

### Requirement: vcip_ip_resolved action is NOT fired when REMOTE_ADDR does not change
The system SHALL NOT fire the `vcip_ip_resolved` action when the connecting address is not a trusted proxy and no replacement occurs.

#### Scenario: vcip_ip_resolved action is not fired when no change occurs
- **WHEN** the plugin boots and REMOTE_ADDR is not a trusted proxy
- **THEN** the `vcip_ip_resolved` action SHALL NOT have been fired
