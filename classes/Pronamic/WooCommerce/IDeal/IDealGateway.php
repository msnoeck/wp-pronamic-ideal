<?php

/**
 * Title: WooCommerce iDEAL gateway
 * Description: 
 * Copyright: Copyright (c) 2005 - 2011
 * Company: Pronamic
 * @author Remco Tolsma
 * @version 1.0
 */
class Pronamic_WooCommerce_IDeal_IDealGateway extends woocommerce_payment_gateway {
	/**
	 * The unique ID of this payment gateway
	 * 
	 * @var string
	 */
	const ID = 'pronamic_ideal';

	//////////////////////////////////////////////////

	/**
	 * Constructs and initialize an iDEAL gateway
	 */
    public function __construct() { 
		$this->id = self::ID;
		$this->method_title = __('Pronamic iDEAL', Pronamic_WordPress_IDeal_Plugin::TEXT_DOMAIN);
		$this->icon = plugins_url('images/icon-24x24.png', Pronamic_WordPress_IDeal_Plugin::$file);
		$this->has_fields = false;
		
		// Load the form fields.
		$this->init_form_fields();
		
		// Load the settings.
		$this->init_settings();
		
		// Define user set variables
		$this->title = $this->settings['title'];
		$this->description = $this->settings['description'];
		$this->configurationId = $this->settings['configuration_id'];
		
		// Actions
		add_action('woocommerce_update_options_payment_gateways', array(&$this, 'process_admin_options'));

		add_action('woocommerce_receipt_' . self::ID, array(&$this, 'receiptPage'));
    } 

	/**
     * Initialise Gateway Settings Form Fields
     */
    function init_form_fields() {
    	$configurations = Pronamic_WordPress_IDeal_ConfigurationsRepository::getConfigurations();
    	$configurationOptions = array('' => __('&mdash; Select configuration &mdash; ', Pronamic_WordPress_IDeal_Plugin::TEXT_DOMAIN));
    	foreach($configurations as $configuration) {
    		$configurationOptions[$configuration->getId()] = $configuration->getName();
    	}
    
    	$this->form_fields = array(
    		'enabled' => array(
				'title' => __('Enable/Disable', Pronamic_WordPress_IDeal_Plugin::TEXT_DOMAIN) , 
				'type' => 'checkbox' , 
				'label' => __( 'Enable iDEAL', Pronamic_WordPress_IDeal_Plugin::TEXT_DOMAIN) , 
				'default' => 'yes' 
			) ,  
			'title' => array(
				'title' => __('Title', Pronamic_WordPress_IDeal_Plugin::TEXT_DOMAIN) , 
				'type' => 'text' , 
				'description' => '<br />' . __('This controls the title which the user sees during checkout.', Pronamic_WordPress_IDeal_Plugin::TEXT_DOMAIN) , 
				'default' => __( 'iDEAL', Pronamic_WordPress_IDeal_Plugin::TEXT_DOMAIN) 
			) , 
			'description' => array(
				'title' => __( 'Customer Message', Pronamic_WordPress_IDeal_Plugin::TEXT_DOMAIN) , 
				'type' => 'textarea' , 
				'description' => '<br />' . __( 'Give the customer instructions for paying via iDEAL, and let them know that their order won\'t be shipping until the money is received.', Pronamic_WordPress_IDeal_Plugin::TEXT_DOMAIN) , 
				'default' => __('With iDEAL you can easily pay online in the secure environment of your own bank.', Pronamic_WordPress_IDeal_Plugin::TEXT_DOMAIN)
			) , 
			'configuration_id' => array(
				'title' => __( 'Configuration', Pronamic_WordPress_IDeal_Plugin::TEXT_DOMAIN) , 
				'type' => 'select' , 
				'description' => '<br />' . __( 'Select an iDEAL configuration.', Pronamic_WordPress_IDeal_Plugin::TEXT_DOMAIN) , 
				'default' => '' , 
				'options' => $configurationOptions 
			)
		);
    }

	//////////////////////////////////////////////////
    
	/**
	 * Admin Panel Options 
	 * - Options for bits like 'title' and availability on a country-by-country basis
	 *
	 * @since 1.0.0
	 */
	public function admin_options() {
    	?>
    	<h3>
    		<?php _e('Pronamic iDEAL', Pronamic_WordPress_IDeal_Plugin::TEXT_DOMAIN); ?>
    	</h3>
    	
    	<table class="form-table">
    		<?php $this->generate_settings_html(); ?>
		</table>
    	<?php
    }

	//////////////////////////////////////////////////

    /**
	 * There are no payment fields for bacs, but we want to show the description if set.
	 */
	function payment_fields() {
		if($this->description) {
			echo wpautop(wptexturize($this->description));
		}

		$configuration = Pronamic_WordPress_IDeal_ConfigurationsRepository::getConfigurationById($this->configurationId);
		if($configuration !== null) {
			$variant = $configuration->getVariant();

			if($variant !== null && $variant->getMethod() == Pronamic_IDeal_IDeal::METHOD_ADVANCED) {
				$lists = Pronamic_WordPress_IDeal_IDeal::getTransientIssuersLists($configuration);
				
				if($lists) {
					?>
					<p class="pronamic_ideal_issuer">
						<label for="pronamic_ideal_issuer_id">
							<?php _e('Choose your bank', Pronamic_WordPress_IDeal_Plugin::TEXT_DOMAIN); ?>
						</label>
						
						<?php echo Pronamic_IDeal_HTML_Helper::issuersSelect('pronamic_ideal_issuer_id', $lists); ?>
					</p>
					<?php 
				} elseif($error = Pronamic_WordPress_IDeal_IDeal::getError()) {
					?>
					<div class="woocommerce_error">
						<?php echo $error->getConsumerMessage(); ?>
					</div>
					<?php
				} else {
					?>
					<div class="woocommerce_error">
						<?php echo __('Paying with iDEAL is not possible. Please try again later or pay another way.', Pronamic_WordPress_IDeal_Plugin::TEXT_DOMAIN); ?>
					</div>
					<?php 
				}
			}
		}
	}

	//////////////////////////////////////////////////
	
	/**
	 * receipt_page
	 **/
	function receiptPage( $order_id ) {
		$configuration = Pronamic_WordPress_IDeal_ConfigurationsRepository::getConfigurationById($this->configurationId);
		if($configuration !== null) {
			$variant = $configuration->getVariant();
	
			if($variant !== null && $variant->getMethod() == Pronamic_IDeal_IDeal::METHOD_BASIC) {
		
				global $woocommerce;
				
				$order = &new woocommerce_order( $order_id );
		
				echo '<p>'.__('Thank you for your order, please click the button below to pay with iDEAL.', Pronamic_WordPress_IDeal_Plugin::TEXT_DOMAIN).'</p>';
		
				$configuration = Pronamic_WordPress_IDeal_ConfigurationsRepository::getConfigurationById($this->configurationId);
				
				if($configuration !== null) {
					$iDeal = new Pronamic_IDeal_Basic();
					$iDeal->setPaymentServerUrl($configuration->getPaymentServerUrl());
					$iDeal->setMerchantId($configuration->getMerchantId());
					$iDeal->setSubId($configuration->getSubId());
					$iDeal->setLanguage('nl');
					$iDeal->setHashKey($configuration->hashKey);
					$iDeal->setCurrency(get_option('woocommerce_currency'));	
			        $iDeal->setPurchaseId($order_id);
			        $iDeal->setDescription(sprintf(__('Order %s', Pronamic_WordPress_IDeal_Plugin::TEXT_DOMAIN), $order_id));
		
					foreach($order->items as $item) {
						$product = $order->get_product_from_item($item);

						$description = $item['name'];
						
						if(isset($item['item_meta'])) {
							if($meta = woocommerce_get_formatted_variation($item['item_meta'], true)) {
								$description .= ' (' . $meta . ')';
							}
						}
		
			        	$iDealItem = new Pronamic_IDeal_Basic_Item();
		    	    	$iDealItem->setNumber($product->sku);
		        		$iDealItem->setDescription($item['name']);
		        		$iDealItem->setPrice($item['cost']);
		        		$iDealItem->setQuantity($item['qty']);
		
		        		$iDeal->addItem($iDealItem);
					}
					
					if($order->order_shipping > 0) {
			        	$iDealItem = new Pronamic_IDeal_Basic_Item();
		    	    	$iDealItem->setNumber(__('shipping', Pronamic_WordPress_IDeal_Plugin::TEXT_DOMAIN));
		        		$iDealItem->setDescription(__('Shipping', Pronamic_WordPress_IDeal_Plugin::TEXT_DOMAIN));
		        		$iDealItem->setPrice($order->order_shipping);
		        		$iDealItem->setQuantity(1);
		
		        		$iDeal->addItem($iDealItem);
					}

				  	$payment = Pronamic_WordPress_IDeal_PaymentsRepository::getPaymentBySource('woocommerce', $order->id);
    	
    				if($payment == null) {
				        // Update payment
						$transaction = new Pronamic_IDeal_Transaction();
						$transaction->setAmount($iDeal->getAmount()); 
						$transaction->setCurrency($iDeal->getCurrency());
						$transaction->setLanguage('nl');
						$transaction->setEntranceCode(uniqid());
						$transaction->setDescription($iDeal->getDescription());
			
						$payment = new Pronamic_WordPress_IDeal_Payment();
						$payment->configuration = $configuration;
						$payment->transaction = $transaction;
						$payment->setSource('woocommerce', $order_id);
			
						$updated = Pronamic_WordPress_IDeal_PaymentsRepository::updatePayment($payment);
    				}
		
			        // HTML
			        $html  = '';
			        $html .= sprintf('<form method="post" action="%s">', esc_attr($iDeal->getPaymentServerUrl()));
			        $html .= 	$iDeal->getHtmlFields();
			        $html .= 	sprintf('<input class="ideal-button" type="submit" name="ideal" value="%s" />', __('Pay with iDEAL', Pronamic_WordPress_IDeal_Plugin::TEXT_DOMAIN));
			        $html .= '</form>';
			        
			        echo $html;
				}
			}
		}
	}

	//////////////////////////////////////////////////

    /**
    * Process the payment and return the result
    **/
    function process_payment( $order_id ) {
    	global $woocommerce;
    	
		$order = &new woocommerce_order( $order_id );

		$configuration = Pronamic_WordPress_IDeal_ConfigurationsRepository::getConfigurationById($this->configurationId);
		if($configuration !== null) {
			$variant = $configuration->getVariant();
	
			if($variant !== null) {
				switch($variant->getMethod()) {
					case Pronamic_IDeal_IDeal::METHOD_BASIC:
						return $this->processIDealBasicPayment($order, $configuration, $variant);
					case Pronamic_IDeal_IDeal::METHOD_ADVANCED:
						return $this->processIDealAdvancedPayment($order, $configuration, $variant);
				}
			}
		}
    }
    
    private function processIDealBasicPayment($order, $configuration, $variant) {
		return array(
			'result' 	=> 'success',
			'redirect'	=> add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(get_option('woocommerce_pay_page_id'))))
		);
    }
    
    private function processIDealAdvancedPayment($order, $configuration, $variant) {
    	$payment = Pronamic_WordPress_IDeal_PaymentsRepository::getPaymentBySource('woocommerce', $order->id);
    	
    	if($payment == null) {
			$transaction = new Pronamic_IDeal_Transaction();
			$transaction->setAmount($order->order_total); 
			$transaction->setCurrency(get_option('woocommerce_currency'));
			$transaction->setExpirationPeriod('PT1H');
			$transaction->setLanguage('nl');
			$transaction->setEntranceCode(uniqid());
			$transaction->setDescription(sprintf(__('Order %s', Pronamic_WordPress_IDeal_Plugin::TEXT_DOMAIN), $order->id));
	
			$payment = new Pronamic_WordPress_IDeal_Payment();
			$payment->configuration = $configuration;
			$payment->transaction = $transaction;
			$payment->setSource('woocommerce', $order->id);
	
			$updated = Pronamic_WordPress_IDeal_PaymentsRepository::updatePayment($payment);
    	}

		$url = Pronamic_WordPress_IDeal_IDeal::handleTransaction($issuerId, $payment, $variant);

		return array(
			'result' 	=> 'success',
			'redirect'	=> $url
		);
    }
}