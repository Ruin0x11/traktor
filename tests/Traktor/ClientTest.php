<?php
use PHPUnit\Framework\TestCase;

use Mockery as m;

class ClientTest extends TestCase
{
    public function tearDown() : void
    {
        m::close();
    }


    public function testSettingApiKey() : void
    {
        $t = new Traktor\Client;
        $t->setApiKey('foobar');

        $this->assertSame('foobar', $t->getApiKey());
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testGetRequestForSingleObject() : void
    {
        $response = new stdClass;
        $response->foo = 'bar';

        $r = $this->getResponseMock(200, $response);
        $c = $this->getGuzzleGetClientMock($r);

        $t = new Traktor\Client($c);
        $t->setApiKey('foobar');

        $decoded = $t->get('foo.bar');

        $this->assertSame('bar', $decoded->foo);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testGetRequestForArrayOfObjects() : void
    {
        $resp1 = new stdClass;
        $resp1->foo = 'bar';

        $resp2 = new stdClass;
        $resp2->foo = 'baz';

        $response = [$resp1, $resp2];

        $r = $this->getResponseMock(200, $response);
        $c = $this->getGuzzleGetClientMock($r);

        $t = new Traktor\Client($c);
        $t->setApiKey('foobar');

        $decoded = $t->get('foo.bar');

        $this->assertSame('bar', $decoded[0]->foo);
        $this->assertSame('baz', $decoded[1]->foo);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testExceptionOnAuthorizationError() : void
    {
        $this->expectException(Traktor\Exception\AuthorizationException::class);

        $response = new stdClass;
        $response->status = 'failure';
        $response->error = 'authorization mock';

        $r = $this->getResponseMock(401, $response);
        $c = $this->getGuzzleGetClientMock($r);

        $t = new Traktor\Client($c);
        $t->setApiKey('foobar');

        $result = $t->get('foo.bar');
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testExceptionOnAvailabilityError() : void
    {
        $this->expectException(Traktor\Exception\AvailabilityException::class);

        $response = new stdClass;
        $response->status = 'failure';
        $response->error = 'downtime mock';

        $r = $this->getResponseMock(503, $response);
        $c = $this->getGuzzleGetClientMock($r);

        $t = new Traktor\Client($c);
        $t->setApiKey('foobar');

        $result = $t->get('foo.bar');
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testExceptionOnBadMethodCall() : void
    {
        $this->expectException(Traktor\Exception\UnknownMethodException::class);

        $response = new stdClass;
        $response->error = 'bar';

        $r = $this->getResponseMock(404, $response);
        $r->shouldReceive('getBody')->andReturn('mock body');

        $c = $this->getGuzzleGetClientMock($r);

        $t = new Traktor\Client($c);
        $t->setApiKey('foobar');

        $result = $t->get('foo.bar');
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testExceptionOnUnknownError() : void
    {
        $this->expectException(Traktor\Exception\RequestException::class);

        $response = new stdClass;
        $response->foo = 'bar';

        $r = $this->getResponseMock(900, $response);
        $r->shouldReceive('getBody')->andReturn('mock body');

        $c = $this->getGuzzleGetClientMock($r);

        $t = new Traktor\Client($c);
        $t->setApiKey('foobar');

        $result = $t->get('foo.bar');
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testExceptionOnMissingApiKey() : void
    {
        $this->expectException(Traktor\Exception\MissingApiKeyException::class);
        $t = new Traktor\Client;

        $result = $t->get('foo.bar');
    }

    protected function getResponseMock($statusCode, $json)
    {
        $r = m::mock('alias:GuzzleHttp\Message\ResponseInterface');
        $r->shouldReceive('getStatusCode')->andReturn($statusCode);
        $r->shouldReceive('json')->andReturn($json);

        return $r;
    }

    protected function getGuzzleGetClientMock($response)
    {
        $c = m::mock('alias:GuzzleHttp\Client');
        $c->shouldReceive('get')->andReturn($response);

        return $c;
    }
    
}
