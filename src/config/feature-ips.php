<?php
return
	sprintf(
	"---
slug: 'ips'
properties:
  name: '%s'
  show_feature_menu_item: true
  storage_key: 'ips' # should correspond exactly to that in the plugin-spec.yaml
  tagline: '%s'
requirements:
  php:
    version: '5.3.6'
    functions:
      - 'filter_var'
    constants:
      - 'FILTER_VALIDATE_IP'
      - 'FILTER_FLAG_IPV4'
      - 'FILTER_FLAG_IPV6'
# Options Sections
sections:
  -
    slug: 'section_enable_plugin_feature_ips'
    primary: true
  -
    slug: 'section_non_ui'
    hidden: true

# Define Options
options:
  -
    key: 'enable_ips'
    section: 'section_enable_plugin_feature_ips'
    default: 'Y'
    type: 'checkbox'
    link_info: ''
    link_blog: ''
  -
    key: 'current_plugin_version'
    section: 'section_non_ui'
",
		_wpsf__( 'IP Manager' ),
		_wpsf__( 'Manage Visitor IP Address' )
	);