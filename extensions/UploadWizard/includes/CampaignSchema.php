<?php
return array(
	"type" => "object",
	"id" => "#campaignnode",
	"required" => true,
	"properties" => array(
		"title" => array(
			"type" => "string"
		),
		"description" => array(
			"type" => "string"
		),
		"enabled" => array(
			"type" => "boolean",
			"required" => true,
		),
		"start" => array(
			"type" => "string",
		),
		"end" => array(
			"type" => "string",
		),
		"autoAdd" => array(
			"type" => "object",
			"properties" => array(
				"categories" => array(
					"type" => "array",
					"items" => array(
						array(
							"type" => "string"
						)
					)
				),
				"wikitext" => array(
					"type" => "string"
				)
			)
		),
		"fields" => array(
			"type" => "array",
			"items" => array(
				array(
					"type" => "object",
					"properties" => array(
						"wikitext" => array(
							"type" => "string"
						),
						"label" => array(
							"type" => "string"
						),
						"maxLength" => array(
							"type" => "integer"
						),
						"initialValue" => array(
							"type" => "string"
						),
						"required" => array(
							"type" => "boolean"
						),
						"type" => array(
							"type" => "string"
						),
						"options" => array(
							"type" => "object",
							"properties" => array(),
							"additionalProperties" => true
						)
					)
				)
			)
		),
		"defaults" => array(
			"type" => "object",
			"properties" =>array(
				"alt" => array(
					"type" => "number"
				),
				"categories" => array(
					"type" => "array",
					"items" => array(
						array(
							"type" => "string"
						)
					)
				),
				"description" => array(
					"type" => "string"
				),
				"lat" => array(
					"type" => "number"
				),
				"lon" => array(
					"type" => "number"
				)
			)
		),
		"display" => array(
			"type" => "object",
			"properties" =>array(
				"headerLabel" => array(
					"type" => "string"
				),
				"thanksLabel" => array(
					"type" => "string"
				),
				"homeButton" => array(
					"type" => "object",
					"properties" => array(
						"label" => array(
							"type" => "string"
						),
						"target" => array(
							"type" => "string"
						)
					)
				),
				"beginButton" => array(
					"type" => "object",
					"properties" => array(
						"label" => array(
							"type" => "string"
						),
						"target" => array(
							"type" => "string"
						)
					)
				),
				"labelPickImage" => array(
					"type" => "string"
				),
				"noticeExistingImage" => array(
					"type" => "string"
				),
				"noticeUpdateDelay" => array(
					"type" => "string"
				)
			)
		),
		"licensing" => array(
			"type" => "object",
			"properties" =>array(
				"defaultType" => array(
					"type" => "string"
				),
				"ownWorkDefault" => array(
					"type" => "string"
				),
				"ownWork" => array(
					"type" => "object",
					"properties" =>array(
						"default" => array(
							"type" => "string",
							"enum" => array_keys( UploadWizardConfig::getSetting( 'licenses' ) )
						),
						"licenses" => array(
							"type" => "array",
							"items" =>array(
								array(
									"type" => "string",
									"enum" => array_keys( UploadWizardConfig::getSetting( 'licenses' ) )
								)
							)


						),
						"template" => array(
							"type" => "string"
						),
						"type" => array(
							"type" => "string"
						)
					)
				),
				"thirdParty" => array(
					"type" => "object",
					"properties" =>array(
						"defaults" => array(
							"type" => "string",
							"enum" => array_keys( UploadWizardConfig::getSetting( 'licenses' ) )
						),
						"licenseGroups" => array(
							"type" => "array",
							"items" =>array(
								array(
									"type" => "object",
									"properties" =>array(
										"head" => array(
											"type" => "string"
										),
										"licenses" => array(
											"type" => "array",
											"items" =>array(
												array(
													"type" => "string",
													"enum" => array_keys( UploadWizardConfig::getSetting( 'licenses' ) )
												)
											)


										),
										"subhead" => array(
											"type" => "string"
										)
									)
								)
							)


						),
						"type" => array(
							"type" => "string"
						)
					)
				)
			)
		),
		"tutorial" => array(
			"type" => "object",
			"properties" =>array(
				"skip" => array(
					"type" => "boolean"
				),
				"helpdeskCoords" => array(
					"type" => "string"
				),
				"template" => array(
					"type" => "string"
				),
				"width" => array(
					"type" => "number"
				)
			)
		),
		"whileActive" => array(
			"type" => "object",
			"properties" => array(
				"display" => array(
					"type" => "object",
					"properties" =>array(
						"headerLabel" => array(
							"type" => "string"
						),
						"thanksLabel" => array(
							"type" => "string"
						),
					),
				),

				"autoAdd" => array(
					"type" => "object",
					"properties" => array(
						"categories" => array(
							"type" => "array",
							"items" => array(
								array(
									"type" => "string"
								),
							),
						),
						"wikitext" => array(
							"type" => "string"
						),
					),
				),
			),
		),
		"beforeActive" => array(
			"type" => "object",
			"properties" => array(
				"display" => array(
					"type" => "object",
					"properties" =>array(
						"headerLabel" => array(
							"type" => "string"
						),
						"thanksLabel" => array(
							"type" => "string"
						),
					),
				),
			),
		),
		"afterActive" => array(
			"type" => "object",
			"properties" => array(
				"display" => array(
					"type" => "object",
					"properties" =>array(
						"headerLabel" => array(
							"type" => "string"
						),
						"thanksLabel" => array(
							"type" => "string"
						),
					),
				),
			),
		),
	)
);
