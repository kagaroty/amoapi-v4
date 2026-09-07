<?php

declare(strict_types=1);

namespace Ufee\AmoV4\Tests\Unit\Api;

use Ufee\AmoV4\Tests\Support\LocalHttpServer;
use Ufee\AmoV4\Tests\TestCase;

class QueryContextUserTest extends TestCase
{
	/** @var LocalHttpServer|null */
	private $server;

	protected function tearDown(): void
	{
		if ($this->server) {
			$this->server->stop();
			$this->server = null;
		}
		parent::tearDown();
	}

	public function testInstanceContextUserAppliedToQueries(): void
	{
		$api = $this->makeStubApiClient();
		$api->asUser(9876543);
		$this->assertSame(9876543, $api->getContextUser());

		$query = $api->query('GET', '/api/v4/leads');
		$query->pushResponse(200, ['id' => 1]);
		$query->execute();

		$this->assertSame(9876543, $query->getContextUser());
		$this->assertSame(9876543, $query->headers['X-Context-User-ID']);
		$this->assertContains('X-Context-User-ID: 9876543', $query->getHeaders());
	}

	public function testServiceQueriesUseContextUser(): void
	{
		$api = $this->makeStubApiClient();
		$api->asUser(777);
		$api->pushResponse(200, ['id' => 5, 'name' => 'Lead']);

		$api->leads()->find(5);

		$this->assertSame(777, $api->lastQuery->headers['X-Context-User-ID']);
	}

	public function testQueryContextUserOverridesInstanceValue(): void
	{
		$api = $this->makeStubApiClient();
		$api->asUser(111);

		$query = $api->query('GET', '/api/v4/leads');
		$query->setContextUser(222);
		$query->pushResponse(200, ['id' => 1]);
		$query->execute();

		$this->assertSame(222, $query->headers['X-Context-User-ID']);
	}

	public function testQueryContextUserNullDisablesInstanceValue(): void
	{
		$api = $this->makeStubApiClient();
		$api->asUser(111);

		$query = $api->query('GET', '/api/v4/leads');
		$query->setContextUser(null);
		$query->pushResponse(200, ['id' => 1]);
		$query->execute();

		$this->assertNull($query->getContextUser());
		$this->assertArrayNotHasKey('X-Context-User-ID', $query->headers);
	}

	public function testNoHeaderWithoutContextUser(): void
	{
		$api = $this->makeStubApiClient();

		$query = $api->query('GET', '/api/v4/leads');
		$query->pushResponse(200, ['id' => 1]);
		$query->execute();

		$this->assertNull($query->getContextUser());
		$this->assertArrayNotHasKey('X-Context-User-ID', $query->headers);
	}

	public function testContextUserChangesQueryHash(): void
	{
		$api = $this->makeApiClient();

		$plain = $api->query('GET', '/api/v4/leads')->generateHash();
		$ctx = $api->query('GET', '/api/v4/leads')->setContextUser(555)->generateHash();

		$this->assertNotSame($plain, $ctx);
	}

	public function testInvalidContextUserRejected(): void
	{
		$api = $this->makeApiClient();

		$this->expectException(\InvalidArgumentException::class);
		$api->query('GET', '/api/v4/leads')->setContextUser(0);
	}

	public function testInvalidInstanceContextUserRejected(): void
	{
		$api = $this->makeApiClient();

		$this->expectException(\InvalidArgumentException::class);
		$api->asUser('user');
	}

	public function testHeaderIsSentOverHttp(): void
	{
		$this->server = new LocalHttpServer();
		$api = $this->makeApiClient();
		$api->oauth->setLongToken('local-token');
		$api->setParam('query_delay', 0);
		$api->callbacks->off('query.response.code');
		$api->asUser(4242);

		$query = $api->query('GET', $this->server->url('/api/v4/leads'));
		$query->prepare();
		$this->assertTrue($query->execute());
		$this->assertSame('4242', $query->response->parseJson()->context_user);

		$query = $api->query('GET', $this->server->url('/api/v4/leads'));
		$query->setContextUser(null);
		$query->prepare();
		$this->assertTrue($query->execute());
		$this->assertNull($query->response->parseJson()->context_user);
	}
}
