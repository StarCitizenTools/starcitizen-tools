<?php

class CheckUserEncryptedData {

	// The data symmetrically encrypted with a random key
	public $encString;

	// Symmetric key, encrypted with the public key
	public $envKeys;

	// algorithm name, passed into openssl 'method' param. Kept as a variable here in case
	// the class definition needs to change, and we have serialized objects stored.
	private $algName;

	// Hash of the public key, in case you've used multiple keys, and need to identify the
	// correct private key
	private $keyHash;

	/**
	 * Create an EncryptedData object from
	 *
	 * @param $data Mixed: data/object to be encryted
	 * @param $publicKey: public key for encryption
	 */
	public function __construct( $data, $publicKey, $algorithmName = 'rc4' ) {
		$this->keyHash = crc32( $publicKey );
		$this->algName = $algorithmName;
		$this->encryptData( serialize( $data ), $publicKey );
	}

	/**
	 * Decrypt the text in this object
	 *
	 * @param $privateKey String with ascii-armored block, or the return of openssl_get_privatekey
	 * @return String plaintext
	 */
	public function getPlaintext( $privateKey ) {
		$result = openssl_open(
			$this->encString,
			$plaintextData,
			$this->envKeys,
			$privateKey,
			$this->algName
		);

		if ( $result == false ) {
			return false;
		}

		return unserialize( $plaintextData );
	}

	/**
	 * Encrypt data with a public key
	 *
	 * @param $data String
	 * @param $publicKey String with ascii-armored block, or the return of openssl_get_publickey
	 * @return String plaintext
	 */
	private function encryptData( $data, $publicKey ) {
		openssl_seal( $data, $encryptedString, $envelopeKeys, array( $publicKey ), $this->algName );
		$this->encString = $encryptedString;
		$this->envKeys = $envelopeKeys[0];
	}
}
