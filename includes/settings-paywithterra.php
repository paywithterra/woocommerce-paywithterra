<?php
/**
 * Settings for PaywithTerra Payment Gateway.
 */

defined( 'ABSPATH' ) || exit;

return array(
	'address'           => array(
		'title'       => 'Terra address',
		'type'        => 'text',
		'description' => 'Your shop wallet address on the Terra blockchain to receiving payments.',
	),
	/**
	 * @uses WC_PaywithTerra_Gateway::generate_paywithterra_form_asset_html
	 */
	'denom'             => array(
		'title'   => 'Denom for payment (token)',
		'type'    => 'paywithterra_form_asset',
		'default' => 'uluna',
	),
	'denom_rate'        => array(
		'title'             => 'Denom exchange rate',
		'type'              => 'number',
		'default'           => '1',
		'custom_attributes' => array(
			'min'  => '0.0001',
			'step' => 'any',
		),
		'description'       => 'Order amount in the selected token will be multiplied by this value to get the
 amount in Denom.',
	),
	'network'           => array(
		'title'       => 'Network',
		'type'        => 'select',
		'description' => 'Select the Terra network type to use.',
		'options'     => array(
			'mainnet' => 'Mainnet',
			'testnet' => 'Testnet',
			//'classic'  => 'Classic',
		),
	),
	'prefix'            => array(
		'title'       => 'Memo prefix',
		'type'        => 'text',
		'default'     => 'ORD-',
		'description' => 'Your shop memo prefix on the Terra blockchain to receiving payments.',
	),
	'tx_link_template'  => array(
		'title'       => 'Finder transaction URL',
		'type'        => 'text',
		'default'     => 'https://finder.terra.money/{network}/tx/',
		'description' => 'Used to generate a link to the transaction on the Terra blockchain.
 You can use placeholder <code>{network}</code>. The tx_hash will be added to the end of the link.',
	),
	'enabled'           => array(
		'title'       => 'Enable/Disable',
		'label'       => 'Enable PaywithTerra Gateway',
		'type'        => 'checkbox',
		'description' => '',
		'default'     => 'no'
	),
	'title'             => array(
		'title'       => 'Title',
		'type'        => 'text',
		'description' => 'This controls the title which the user sees during checkout.',
		'default'     => 'PaywithTerra',
	),
	'description'       => array(
		'title'       => 'Description',
		'type'        => 'textarea',
		'description' => 'This controls the description which the user sees during checkout.',
		'default'     => '',
	),
	'log_enabled'       => array(
		'title'       => 'Error logging',
		'label'       => 'Enable logging for errors while interaction with PaywithTerra API',
		'type'        => 'checkbox',
		'description' => '',
		'default'     => 'no'
	),
	'icon'              => array(
		'title'       => 'Public icon',
		'type'        => 'select',
		'default'     => 'logo',
		'options'     => array(
			'logo'       => "PaywithTerra logo",
			'icon-rect'  => "PaywithTerra icon rectangle",
			'custom-url' => "Custom icon",
		),
		'description' => 'Shown on payment method selection page',
	),
	'custom_icon_url'   => array(
		'title'       => 'Custom icon',
		'type'        => 'text',
		'placeholder' => 'https://',
		'description' => '<b>Full url</b> (from http) to custom payment icon image.',
		'default'     => '',
	),
	'disable_ssl_check' => array(
		'title'       => 'Disable SSL Check',
		'label'       => 'Enable this option to skip SSL check while connecting to PaywithTerra Gateway',
		'type'        => 'checkbox',
		'description' => '',
		'default'     => 'no'
	),
);