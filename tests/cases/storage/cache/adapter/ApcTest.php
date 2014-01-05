<?php
/**
 * Lithium: the most rad php framework
 *
 * @copyright     Copyright 2013, Union of RAD (http://union-of-rad.org)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace lithium\tests\cases\storage\cache\adapter;

use lithium\storage\cache\adapter\Apc;

class ApcTest extends \lithium\test\Unit {

	/**
	 * Skip the test if APC extension is unavailable.
	 *
	 * @return void
	 */
	public function skip() {
		$this->skipIf(!Apc::enabled(), 'APC is either not loaded or not enabled.');
	}

	public function setUp() {
		apc_clear_cache('user');
		$this->Apc = new Apc();
	}

	public function tearDown() {
		apc_clear_cache('user');
		unset($this->Apc);
	}

	public function testEnabled() {
		$apc = $this->Apc;
		$this->assertTrue($apc::enabled());
	}

	public function testSimpleWrite() {
		$key = 'key';
		$data = 'value';
		$keys = array($key => $data);
		$expiry = '+5 seconds';

		$closure = $this->Apc->write($keys, $expiry);
		$this->assertInternalType('callable', $closure);

		$params = compact('keys', 'expiry');
		$result = $closure($this->Apc, $params);
		$this->assertTrue($result);

		$expected = $data;
		$result = apc_fetch($key);
		$this->assertEqual($expected, $result);

		$result = apc_delete($key);
		$this->assertTrue($result);

		$key = 'another_key';
		$data = 'more_data';
		$keys = array($key => $data);
		$expiry = '+1 minute';

		$closure = $this->Apc->write($keys, $expiry);
		$this->assertInternalType('callable', $closure);

		$expected = $keys;
		$params = compact('keys', 'expiry');
		$result = $closure($this->Apc, $params);
		$this->assertTrue($result);

		$expected = $data;
		$result = apc_fetch($key);
		$this->assertEqual($expected, $result);

		$result = apc_delete($key);
		$this->assertTrue($result);
	}

	/**
	 * Tests that an item can be written to the cache using
	 * the default expiration.
	 *
	 * Note that because of the nature of APC we cannot test if an item
	 * correctly expires. Expiration checks are done by APC only on each
	 * _page request_.
	 */
	public function testWriteExpiryDefault() {
		$apc = new Apc(array('expiry' => '+5 seconds'));
		$keys = array('key1' => 'data1');
		$expiry = null;
		$closure = $apc->write($keys, $expiry);
		$closure($apc, compact('keys', 'expiry'));

		$result = apc_exists('key1');
		$this->assertTrue($result);
	}

	/**
	 * Tests that an item can be written to the cache using
	 * `strtotime` syntax.
	 *
	 * Note that because of the nature of APC we cannot test if an item
	 * correctly expires. Expiration checks are done by APC only on each
	 * _page request_.
	 */
	public function testWriteExpiryExpires() {
		$keys = array('key1' => 'data1');
		$expiry = '+5 seconds';
		$closure = $this->Apc->write($keys, $expiry);
		$closure($this->Apc, compact('keys', 'expiry'));

		$result = apc_exists('key1');
		$this->assertTrue($result);
	}

	/**
	 * Tests that an item can be written to the cache using
	 * TTL syntax.
	 *
	 * Note that because of the nature of APC we cannot test if an item
	 * correctly expires. Expiration checks are done by APC only on each
	 * _page request_.
	 */
	public function testWriteExpiryTtl() {
		$keys = array('key1' => 'data1');
		$expiry = 5;
		$closure = $this->Apc->write($keys, $expiry);
		$closure($this->Apc, compact('keys', 'expiry'));

		$result = apc_exists('key1');
		$this->assertTrue($result);
	}

	public function testWriteMulti() {
		$expiry = '+1 minute';
		$keys = array(
			'key1' => 'data1',
			'key2' => 'data2',
			'key3' => 'data3'
		);
		$closure = $this->Apc->write($keys, $expiry);
		$result = $closure($this->Apc, compact('keys', 'expiry'));
		$this->assertTrue($result);

		$result = apc_fetch(array_keys($keys));
		$this->assertEqual($keys, $result);

		$result = apc_delete(array_keys($keys));
		$this->assertEqual(array(), $result);
	}

	public function testSimpleRead() {
		$key = 'read_key';
		$data = 'read data';
		$keys = array($key);

		$result = apc_store($key, $data, 60);
		$this->assertTrue($result);

		$closure = $this->Apc->read($keys);
		$this->assertInternalType('callable', $closure);

		$expected = array($key => $data);
		$params = compact('keys');
		$result = $closure($this->Apc, $params);
		$this->assertEqual($expected, $result);

		$result = apc_delete($key);
		$this->assertTrue($result);

		$key = 'another_read_key';
		$data = 'read data';
		$keys = array($key);

		$result = apc_store($key, $data, 60);
		$this->assertTrue($result);

		$closure = $this->Apc->read($keys);
		$this->assertInternalType('callable', $closure);

		$expected = array($key => $data);
		$params = compact('keys');
		$result = $closure($this->Apc, $params, null);
		$this->assertEqual($expected, $result);

		$result = apc_delete($key);
		$this->assertTrue($result);
	}

	public function testReadMulti() {
		$keys = array(
			'key1' => 'data1',
			'key2' => 'data2',
			'key3' => 'data3'
		);
		apc_store($keys, null, 60);

		$expected = array(
			'key1' => 'data1',
			'key2' => 'data2',
			'key3' => 'data3'
		);
		$keys = array(
			'key1',
			'key2',
			'key3'
		);
		$closure = $this->Apc->read($keys);
		$result = $closure($this->Apc, compact('keys'));
		$this->assertEqual($expected, $result);

		$result = apc_delete($keys);
		$this->assertEqual(array(), $result);
	}

	public function testReadKeyThatDoesNotExist() {
		$key = 'does_not_exist';
		$keys = array($key);
		$closure = $this->Apc->read($keys);

		$expected = array();
		$result = $closure($this->Apc, compact('keys'));
		$this->assertIdentical($expected, $result);
	}

	public function testWriteAndReadNull() {
		$expiry = '+1 minute';
		$keys = array(
			'key1' => null
		);
		$result = $this->Apc->write($keys);
		$this->assertTrue($result($this->Apc, compact('keys', 'expiry')));

		$expected = $keys;
		$result = $this->Apc->read(array_keys($keys));
		$this->assertEqual($expected, $result($this->Apc, array('keys' => array_keys($keys))));
	}

	public function testWriteAndReadNullMulti() {
		$expiry = '+1 minute';
		$keys = array(
			'key1' => null,
			'key2' => 'data2'
		);
		$result = $this->Apc->write($keys);
		$this->assertTrue($result($this->Apc, compact('keys', 'expiry')));

		$expected = $keys;
		$result = $this->Apc->read(array_keys($keys));
		$this->assertEqual($expected, $result($this->Apc, array('keys' => array_keys($keys))));

		$keys = array(
			'key1' => null,
			'key2' => null
		);
		$result = $this->Apc->write($keys);
		$this->assertTrue($result($this->Apc, compact('keys', 'expiry')));
	}

	public function testWriteAndReadArray() {
		$expiry = '+1 minute';
		$keys = array(
			'key1' => array('foo' => 'bar')
		);
		$result = $this->Apc->write($keys);
		$this->assertTrue($result($this->Apc, compact('keys', 'expiry')));

		$expected = $keys;
		$result = $this->Apc->read(array_keys($keys));
		$this->assertEqual($expected, $result($this->Apc, array('keys' => array_keys($keys))));
	}

	public function testDelete() {
		$key = 'delete_key';
		$data = 'data to delete';
		$keys = array($key);

		$result = apc_store($key, $data, 60);
		$this->assertTrue($result);

		$closure = $this->Apc->delete($keys);
		$this->assertInternalType('callable', $closure);

		$params = compact('keys');
		$result = $closure($this->Apc, $params);
		$this->assertTrue($result);
	}

	public function testDeleteMulti() {
		$expiry = '+1 minute';
		$keys = array(
			'key1' => 'data1',
			'key2' => 'data2',
			'key3' => 'data3'
		);
		apc_store($keys, null, 60);

		$keys = array(
			'key1',
			'key2',
			'key3'
		);
		$closure = $this->Apc->delete($keys);
		$result = $closure($this->Apc, compact('keys'));
		$this->assertTrue($result);

		$result = apc_delete($keys);
		$this->assertEqual($keys, $result);
	}

	public function testDeleteNonExistentKey() {
		$key = 'delete_key';
		$data = 'data to delete';
		$keys = array($key);

		$closure = $this->Apc->delete($keys);
		$this->assertInternalType('callable', $closure);

		$params = compact('keys');
		$result = $closure($this->Apc, $params);
		$this->assertFalse($result);
	}

	public function testWriteReadAndDeleteRoundtrip() {
		$key = 'write_read_key';
		$data = 'write/read value';
		$keys = array($key => $data);
		$expiry = '+5 seconds';

		$closure = $this->Apc->write($keys, $expiry);
		$this->assertInternalType('callable', $closure);

		$params = compact('keys', 'expiry');
		$result = $closure($this->Apc, $params);
		$this->assertTrue($result);

		$expected = $data;
		$result = apc_fetch($key);
		$this->assertEqual($expected, $result);

		$closure = $this->Apc->read(array_keys($keys));
		$this->assertInternalType('callable', $closure);

		$expected = $keys;
		$params = array('keys' => array($key));
		$result = $closure($this->Apc, $params);
		$this->assertEqual($expected, $result);

		$closure = $this->Apc->delete(array_keys($keys));
		$this->assertInternalType('callable', $closure);

		$params = array('keys' => array($key));
		$result = $closure($this->Apc, $params);
		$this->assertTrue($result);
	}

	public function testClear() {
		$key1 = 'key_clear_1';
		$key2 = 'key_clear_2';

		$result = apc_store($key1, 'data that will no longer exist', 60);
		$this->assertTrue($result);

		$result = apc_store($key2, 'more dead data', 60);
		$this->assertTrue($result);

		$result = $this->Apc->clear();
		$this->assertTrue($result);

		$this->assertFalse(apc_fetch($key1));
		$this->assertFalse(apc_fetch($key2));
	}

	public function testDecrement() {
		$key = 'decrement';
		$value = 10;

		$result = apc_store($key, $value, 60);
		$this->assertTrue($result);

		$closure = $this->Apc->decrement($key);
		$this->assertInternalType('callable', $closure);

		$params = compact('key');
		$result = $closure($this->Apc, $params, null);
		$this->assertEqual($value - 1, $result);

		$result = apc_fetch($key);
		$this->assertEqual($value - 1, $result);

		$result = apc_delete($key);
		$this->assertTrue($result);
	}

	public function testDecrementNonIntegerValue() {
		$key = 'non_integer';
		$value = 'no';

		$result = apc_store($key, $value, 60);
		$this->assertTrue($result);

		$closure = $this->Apc->decrement($key);
		$this->assertInternalType('callable', $closure);

		$params = compact('key');
		$result = $closure($this->Apc, $params, null);

		$result = apc_fetch($key);
		$this->assertEqual('no', $result);

		$result = apc_delete($key);
		$this->assertTrue($result);
	}

	public function testIncrement() {
		$key = 'increment';
		$value = 10;

		$result = apc_store($key, $value, 60);
		$this->assertTrue($result);

		$closure = $this->Apc->increment($key);
		$this->assertInternalType('callable', $closure);

		$params = compact('key');
		$result = $closure($this->Apc, $params, null);
		$this->assertEqual($value + 1, $result);

		$result = apc_fetch($key);
		$this->assertEqual($value + 1, $result);

		$result = apc_delete($key);
		$this->assertTrue($result);
	}

	public function testIncrementNonIntegerValue() {
		$key = 'non_integer_increment';
		$value = 'yes';

		$result = apc_store($key, $value, 60);
		$this->assertTrue($result);

		$closure = $this->Apc->increment($key);
		$this->assertInternalType('callable', $closure);

		$params = compact('key');
		$result = $closure($this->Apc, $params, null);

		$result = apc_fetch($key);
		$this->assertEqual('yes', $result);

		$result = apc_delete($key);
		$this->assertTrue($result);
	}
}

?>