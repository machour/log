<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\log {

    function openlog()
    {
        return \yii\log\tests\unit\SyslogTargetTest::openlog(func_get_args());
    }

    function syslog()
    {
        return \yii\log\tests\unit\SyslogTargetTest::syslog(func_get_args());
    }

    function closelog()
    {
        return \yii\log\tests\unit\SyslogTargetTest::closelog(func_get_args());
    }
}

namespace yii\log\tests\unit {

    use PHPUnit_Framework_MockObject_MockObject;
    use Psr\Log\LogLevel;
    use yii\helpers\VarDumper;
    use yii\log\Logger;
    use yii\log\SyslogTarget;
    use yii\tests\TestCase;

    /**
     * Class SyslogTargetTest.
     *
     * @group log
     */
    class SyslogTargetTest extends TestCase
    {
        /**
         * Array of static functions.
         *
         * @var array
         */
        public static $functions = [];

        /**
         * @var PHPUnit_Framework_MockObject_MockObject
         */
        protected $syslogTarget;

        /**
         * Set up syslogTarget as the mock object.
         */
        protected function setUp()
        {
            $this->syslogTarget = $this->getMockBuilder(SyslogTarget::class)
                ->setMethods(['getMessagePrefix'])
                ->getMock();
        }

        /**
         * @covers \yii\log\SyslogTarget::export()
         */
        public function testExport()
        {
            $identity = 'identity string';
            $options = LOG_ODELAY | LOG_PID;
            $facility = 'facility string';
            $messages = [
                [LogLevel::INFO, 'info message'],
                [LogLevel::ERROR, 'error message'],
                [LogLevel::WARNING, 'warning message'],
                [LogLevel::DEBUG, 'trace message'],
                [LogLevel::NOTICE, 'notice message'],
                [LogLevel::EMERGENCY, 'emergency message'],
                [LogLevel::ALERT, 'alert message'],
            ];

            /* @var $syslogTarget SyslogTarget|PHPUnit_Framework_MockObject_MockObject */
            $syslogTarget = $this->getMockBuilder(SyslogTarget::class)
                ->setMethods(['openlog', 'syslog', 'formatMessage', 'closelog'])
                ->getMock();

            $syslogTarget->identity = $identity;
            $syslogTarget->options = $options;
            $syslogTarget->facility = $facility;
            $syslogTarget->messages = $messages;

            $syslogTarget->expects($this->once())
                ->method('openlog')
                ->with(
                    $this->equalTo($identity),
                    $this->equalTo($options),
                    $this->equalTo($facility)
                );

            $syslogTarget->expects($this->exactly(7))
                ->method('formatMessage')
                ->withConsecutive(
                    [$this->equalTo($messages[0])],
                    [$this->equalTo($messages[1])],
                    [$this->equalTo($messages[2])],
                    [$this->equalTo($messages[3])],
                    [$this->equalTo($messages[4])],
                    [$this->equalTo($messages[5])],
                    [$this->equalTo($messages[6])]
                )->willReturnMap([
                    [$messages[0], 'formatted message 1'],
                    [$messages[1], 'formatted message 2'],
                    [$messages[2], 'formatted message 3'],
                    [$messages[3], 'formatted message 4'],
                    [$messages[4], 'formatted message 5'],
                    [$messages[5], 'formatted message 6'],
                    [$messages[6], 'formatted message 7'],
                ]);

            $syslogTarget->expects($this->exactly(7))
                ->method('syslog')
                ->withConsecutive(
                    [$this->equalTo(LOG_INFO), $this->equalTo('formatted message 1')],
                    [$this->equalTo(LOG_ERR), $this->equalTo('formatted message 2')],
                    [$this->equalTo(LOG_WARNING), $this->equalTo('formatted message 3')],
                    [$this->equalTo(LOG_DEBUG), $this->equalTo('formatted message 4')],
                    [$this->equalTo(LOG_NOTICE), $this->equalTo('formatted message 5')],
                    [$this->equalTo(LOG_EMERG), $this->equalTo('formatted message 6')],
                    [$this->equalTo(LOG_ALERT), $this->equalTo('formatted message 7')]
                );

            $syslogTarget->expects($this->once())->method('closelog');

            static::$functions['openlog'] = function ($arguments) use ($syslogTarget) {
                $this->assertCount(3, $arguments);
                [$identity, $option, $facility] = $arguments;
                return $syslogTarget->openlog($identity, $option, $facility);
            };

            static::$functions['syslog'] = function ($arguments) use ($syslogTarget) {
                $this->assertCount(2, $arguments);
                [$priority, $message] = $arguments;
                return $syslogTarget->syslog($priority, $message);
            };

            static::$functions['closelog'] = function ($arguments) use ($syslogTarget) {
                $this->assertCount(0, $arguments);
                return $syslogTarget->closelog();
            };

            $syslogTarget->export();
        }

        /**
         * @covers \yii\log\SyslogTarget::export()
         *
         * See https://github.com/yiisoft/yii2/issues/14296
         */
        public function testFailedExport()
        {
            $syslogTarget = $this->getMockBuilder('yii\\log\\SyslogTarget')
                ->setMethods(['openlog', 'syslog', 'formatMessage', 'closelog'])
                ->getMock();
            $syslogTarget->method('syslog')->willReturn(false);

            $syslogTarget->identity = 'identity string';
            $syslogTarget->options = LOG_ODELAY | LOG_PID;
            $syslogTarget->facility = 'facility string';
            $syslogTarget->messages = [
                [LogLevel::INFO, 'test', []],
            ];

            static::$functions['openlog'] = function ($arguments) use ($syslogTarget) {
                $this->assertCount(3, $arguments);
                [$identity, $option, $facility] = $arguments;
                return $syslogTarget->openlog($identity, $option, $facility);
            };
            static::$functions['syslog'] = function ($arguments) use ($syslogTarget) {
                $this->assertCount(2, $arguments);
                [$priority, $message] = $arguments;
                return $syslogTarget->syslog($priority, $message);
            };
            static::$functions['closelog'] = function ($arguments) use ($syslogTarget) {
                $this->assertCount(0, $arguments);
                return $syslogTarget->closelog();
            };

            $this->expectException('yii\log\LogRuntimeException');
            $syslogTarget->export();
        }

        /**
         * @param $name
         * @param $arguments
         * @return mixed
         */
        public static function __callStatic($name, $arguments)
        {
            if (isset(static::$functions[$name]) && is_callable(static::$functions[$name])) {
                $arguments = $arguments[0] ?? $arguments;
                return forward_static_call(static::$functions[$name], $arguments);
            }
            static::fail("Function '$name' has not implemented yet!");
        }

        /**
         * @covers \yii\log\SyslogTarget::formatMessage()
         */
        public function testFormatMessageWhereTextIsString()
        {
            $message = [LogLevel::INFO, 'text', ['category' => 'category', 'time' => 'timestamp']];

            $this->syslogTarget
                ->expects($this->once())
                ->method('getMessagePrefix')
                ->with($this->equalTo($message))
                ->willReturn('some prefix');

            $result = $this->syslogTarget->formatMessage($message);
            $this->assertEquals('some prefix[info][category] text', $result);
        }

        /**
         * @covers \yii\log\SyslogTarget::formatMessage()
         */
        public function testFormatMessageWhereTextIsException()
        {
            $exception = new \Exception('exception text');
            $message = [LogLevel::INFO, $exception, ['category' => 'category', 'time' => 'timestamp']];

            $this->syslogTarget
                ->expects($this->once())
                ->method('getMessagePrefix')
                ->with($this->equalTo($message))
                ->willReturn('some prefix');

            $result = $this->syslogTarget->formatMessage($message);
            $this->assertEquals('some prefix[info][category] ' . (string) $exception, $result);
        }
    }
}
