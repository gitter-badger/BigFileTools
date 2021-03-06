<?php
/**
 * @testCase
 */
namespace BigFileTools;

require __DIR__ . "/../bootstrap.php";

use Tester\Assert;
use Tester\Environment;
use Tester\TestCase;

class Platform64BitTest extends TestCase {

	protected function setUp()
	{
		parent::setUp(); // TODO: Change the autogenerated stub

		if(!Utils::isPlatformWith64bitInteger()) {
			Environment::skip('This test can run only on platform where PHP has 64-bit integers.');
		}
	}

	public function testFileUnder2_31bites() {
		Assert::equal(
			TESTS_SMALL_FILE_SIZE,
			(string) filesize(TESTS_SMALL_FILE_PATH),
			"Failed for file smaller then 2^31 bites"
		);
	}

	public function testFileBetween2_31and2_32_bites() {
		Assert::equal(
			TESTS_MEDIUM_FILE_SIZE,
			(string) filesize(TESTS_MEDIUM_FILE_PATH), // converting unsinged to signed integer
			"Failed for file between 2^31 and 2^32 bites"
		);
	}

	public function testFileLargerThen2_32bites() {
		Assert::equal(
			TESTS_BIG_FILE_SIZE,
			(string) filesize(TESTS_BIG_FILE_PATH),
			"Failed for file with size over 2^32 bites"
		);
	}

}

(new Platform64BitTest())->run();
