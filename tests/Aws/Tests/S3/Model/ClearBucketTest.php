<?php

namespace Aws\Tests\S3\Model;

use Aws\S3\Model\ClearBucket;
use Guzzle\Common\Exception\ExceptionCollection;

/**
 * @covers Aws\S3\Model\ClearBucket
 */
class ClearBucketTest extends \Guzzle\Tests\GuzzleTestCase
{
    public function testAllowsGettersAndSetters()
    {
        // Ensure that the client and bucket are set by the constructor
        $clear = new ClearBucket($this->getServiceBuilder()->get('s3'), 'foo');
        $this->assertSame($this->getServiceBuilder()->get('s3'), $this->readAttribute($clear, 'client'));

        // Ensure that the bucket can be changed
        $this->assertEquals('foo', $this->readAttribute($clear, 'bucket'));
        $clear->setBucket('bar');
        $this->assertEquals('bar', $this->readAttribute($clear, 'bucket'));

        // Ensure that an MFA token can be set
        $clear->setMfa('test');
        $this->assertEquals('test', $this->readAttribute($clear, 'mfa'));

        // Ensure that the iterator can set explicitly
        $iterator = $this->getMockForAbstractClass('Aws\S3\Iterator\AbstractS3ResourceIterator', array(), '', false);
        $clear->setIterator($iterator);
        $this->assertSame($iterator, $clear->getIterator());
    }

    public function testCreatesDefaultIterator()
    {
        $clear = new ClearBucket($this->getServiceBuilder()->get('s3'), 'foo');
        $this->assertInstanceOf('Aws\S3\Iterator\AbstractS3ResourceIterator', $clear->getIterator());
    }

    public function testHasEvents()
    {
        $this->assertInternalType('array', ClearBucket::getAllEvents());
    }

    public function testClearsBucketUsingDefaultIterator()
    {
        $client = $this->getServiceBuilder()->get('s3');
        $mock = $this->setMockResponse($client, array(
            's3/get_bucket_object_versions_page_2',
            's3/delete_multiple_objects'
        ));

        $clear = new ClearBucket($client, 'foo');
        $this->assertEquals(4, $clear->clear());

        $requests = $mock->getReceivedRequests();
        foreach ($requests as $request) {
            $this->assertEquals('foo.s3.amazonaws.com', $request->getHost());
        }
        $this->assertEquals(2, count($requests));
        $this->assertTrue($requests[0]->getQuery()->hasKey('versions'));
        $this->assertTrue($requests[1]->getQuery()->hasKey('delete'));
    }

    public function testClearsBucketAndBuffersExceptions()
    {
        $client = $this->getServiceBuilder()->get('s3');
        $mock = $this->setMockResponse($client, array(
            's3/get_bucket_object_versions_page_2',
            's3/delete_multiple_objects_errors'
        ));

        $clear = new ClearBucket($client, 'foo');

        try {
            $clear->clear();
            $this->fail('Did not throw expected exception');
        } catch (ExceptionCollection $e) {
            $requests = $mock->getReceivedRequests();
            $this->assertEquals(2, count($requests));
            $this->assertEquals(1, count($e));
            foreach ($e->getIterator() as $ee) {
                $this->assertEquals(1, count($ee->getErrors()));
            }
        }
    }
}
