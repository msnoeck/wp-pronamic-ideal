<?php

/**
 * Title: Easy
 * Description: 
 * Copyright: Copyright (c) 2005 - 2011
 * Company: Pronamic
 * @author Remco Tolsma
 * @version 1.0
 */
class Pronamic_Gateways_IDealEasy_Gateway extends Pronamic_Gateways_Gateway {
	/**
	 * Construct and intialize an iDEAL Easy gateway
	 *  
	 * @param Pronamic_WordPress_IDeal_Configuration $configuration
	 */
	public function __construct( Pronamic_WordPress_IDeal_Configuration $configuration ) {
		parent::__construct( $configuration );

		$this->set_method( Pronamic_Gateways_Gateway::METHOD_HTML_FORM );
		$this->set_has_feedback( false );
		$this->set_amount_minimum( 0.01 );

		$this->client = new Pronamic_Gateways_IDealEasy_IDealEasy();
		$this->client->setPaymentServerUrl( $configuration->getPaymentServerUrl() );
		$this->client->setPspId( $configuration->pspId );
	}
	
	/////////////////////////////////////////////////

	/**
	 * Start transaction with the specified data
	 * 
	 * @see Pronamic_Gateways_Gateway::start()
	 */
	public function start( Pronamic_IDeal_IDealDataProxy $data ) {
		$this->set_transaction_id( md5( time() . $data->getOrderId() ) );
		$this->set_action_url( $this->client->getPaymentServerUrl() );

		$this->client->setLanguage( $data->getLanguageIso639AndCountryIso3166Code() );
		$this->client->setCurrency( $data->getCurrencyAlphabeticCode() );
		$this->client->setOrderId( $data->getOrderId() );
		$this->client->setDescription( $data->getDescription() );
		$this->client->setAmount( $data->getAmount() );
		$this->client->setEMailAddress( $data->getEMailAddress() );
		$this->client->setCustomerName( $data->getCustomerName() );
		$this->client->setOwnerAddress( $data->getOwnerAddress() );
		$this->client->setOwnerCity( $data->getOwnerCity() );
		$this->client->setOwnerZip( $data->getOwnerZip() );
	}

	/////////////////////////////////////////////////

	/**
	 * Get output HTML
	 * 
	 * @see Pronamic_Gateways_Gateway::get_output_html()
	 * @return string
	 */
	public function get_output_html() {
		return $this->client->getHtmlFields();
	}
}
