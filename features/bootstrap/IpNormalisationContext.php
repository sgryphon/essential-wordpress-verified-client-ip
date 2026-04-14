<?php

declare(strict_types=1);

use Behat\Behat\Context\Context;
use Gryphon\VerifiedClientIp\IpUtils;

final class IpNormalisationContext implements Context {

	private string $ip_input = '';

	private ?string $ip_result = null;

	/**
	 * @Given an IP address of :ip
	 */
	public function anIpAddressOf( string $ip ): void {
		$this->ip_input = $ip;
	}

	/**
	 * @When the address is normalised
	 */
	public function theAddressIsNormalised(): void {
		$this->ip_result = IpUtils::normalise( $this->ip_input );
	}

	/**
	 * @Then the result should be :expected
	 */
	public function theResultShouldBe( string $expected ): void {
		if ( $this->ip_result !== $expected ) {
			throw new RuntimeException(
				sprintf( 'Expected "%s" but got "%s".', $expected, (string) $this->ip_result )
			);
		}
	}
}
