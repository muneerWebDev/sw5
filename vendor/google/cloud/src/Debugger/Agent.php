<?php
/**
 * Copyright 2017 Google Inc. All Rights Reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *      http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace Google\Cloud\Debugger;

use Google\Cloud\Core\Batch\BatchRunner;
use Google\Cloud\Core\Batch\BatchTrait;
use Google\Cloud\Debugger\BreakpointStorage\BreakpointStorageInterface;
use Google\Cloud\Debugger\BreakpointStorage\SysvBreakpointStorage;
use Google\Cloud\Logging\LoggingClient;
use Psr\Log\LoggerInterface;

/**
 * This class is responsible for registering all debugger breakpoints and
 * logpoints for each request. It should be created as early as possible in
 * your application.
 *
 * Example:
 * ```
 * use Google\Cloud\Debugger\Agent;
 *
 * $agent = new Agent();
 * ```
 */
class Agent
{
    use BatchTrait;

    /**
     * @var Debuggee
     */
    private static $debuggee;

    /**
     * @var string Unique identifier for the debuggee generated by the
     *      controller service.
     */
    private $debuggeeId;

    /**
     * @var array Associative array of breakpoints indexed by breakpoint id.
     */
    private $breakpointsById = [];

    /**
     * @var string Path to the root directory of the source code.
     */
    private $sourceRoot;

    /**
     * @var LoggerInterface A PSR-3 logger that handles logpoints.
     */
    private $logger;

    /**
     * Create a new Debugger Agent, registers all breakpoints for collection
     * or execution, and registers a shutdown function for reporting results.
     *
     * @param array $options [optional] {
     *      Configuration options.
     *
     *      @type BreakpointStorageInterface $storage Breakpoint storage
     *            to fetch breakpoints from. **Defaults to** a new
     *            SysvBreakpointStorage instance.
     *      @type Debuggee $debuggee Unique debuggee instance. **Defaults to**
     *            a generated debuggee instance.
     *      @type string $sourceRoot Path to the root of the source repository.
     *            **Defaults to** the directory of the calling file.
     *      @type LoggerInterface $logger PSR-3 compliant logger used to write
     *            logpoint records. **Defaults to** a new Stackdriver logger.
     * }
     */
    public function __construct(array $options = [])
    {
        $storage = isset($options['storage'])
            ? $options['storage']
            : $this->defaultStorage();
        list($this->debuggeeId, $breakpoints) = $storage->load();

        $this->setCommonBatchProperties($options + [
            'identifier' => 'stackdriver-debugger',
            'batchMethod' => 'insertBatch'
        ]);
        self::$debuggee = isset($options['debuggee'])
            ? $options['debuggee']
            : $this->defaultDebuggee();
        $this->logger = isset($options['logger'])
            ? $options['logger']
            : $this->defaultLogger();

        $this->sourceRoot = isset($options['sourceRoot'])
            ? $options['sourceRoot']
            : dirname(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0]['file']);

        if (empty($breakpoints)) {
            return;
        }

        if (!extension_loaded('stackdriver_debugger')) {
            trigger_error('Breakpoints set but "stackdriver_debugger" extension not loaded', E_USER_WARNING);
            return;
        }

        foreach ($breakpoints as $breakpoint) {
            $this->breakpointsById[$breakpoint->id()] = $breakpoint;
            switch ($breakpoint->action()) {
                case Breakpoint::ACTION_CAPTURE:
                    $sourceLocation = $breakpoint->location();
                    $this->invalidateOpcache($breakpoint);
                    stackdriver_debugger_add_snapshot(
                        $sourceLocation->path(),
                        $sourceLocation->line(),
                        [
                            'snapshotId'    => $breakpoint->id(),
                            'condition'     => $breakpoint->condition(),
                            'expressions'   => $breakpoint->expressions(),
                            'callback'      => [$this, 'handleSnapshot'],
                            'sourceRoot'    => $this->sourceRoot
                        ]
                    );
                    break;
                case Breakpoint::ACTION_LOG:
                    $sourceLocation = $breakpoint->location();
                    $this->invalidateOpcache($breakpoint);
                    stackdriver_debugger_add_logpoint(
                        $sourceLocation->path(),
                        $sourceLocation->line(),
                        $breakpoint->logLevel(),
                        $breakpoint->logMessageFormat(),
                        [
                            'snapshotId'    => $breakpoint->id(),
                            'condition'     => $breakpoint->condition(),
                            'expressions'   => $breakpoint->expressions(),
                            'callback'      => [$this->logger, 'log'],
                            'sourceRoot'    => $this->sourceRoot
                        ]
                    );
                    break;
                default:
                    continue;
            }
        }
    }

    /**
     * Callback for reporting a snapshot.
     *
     * @access private
     * @param array $snapshot {
     *      Snapshot data
     *
     *      @type string $id The breakpoint id of the snapshot
     *      @type array $evaluatedExpressions The results of evaluating the
     *            snapshot's expressions
     *      @type array $stackframes List of captured stackframe data.
     * }
     */
    public function handleSnapshot(array $snapshot)
    {
        if (array_key_exists($snapshot['id'], $this->breakpointsById)) {
            $breakpoint = $this->breakpointsById[$snapshot['id']];
            $breakpoint->finalize();
            $breakpoint->addEvaluatedExpressions($snapshot['evaluatedExpressions']);
            $breakpoint->addStackFrames($snapshot['stackframes']);
            $this->batchRunner->submitItem($this->identifier, $breakpoint);
        }
    }

    protected function getCallback()
    {
        if (!isset(self::$debuggee)) {
            self::$debuggee = $this->defaultDebuggee();
        }
        return [self::$debuggee, 'updateBreakpointBatch'];
    }

    private function defaultStorage()
    {
        return new SysvBreakpointStorage();
    }

    private function defaultDebuggee()
    {
        $client = new DebuggerClient($this->clientConfig);
        return $client->debuggee($this->debuggeeId);
    }

    private function defaultLogger()
    {
        $client = new LoggingClient();
        return $client->psrBatchLogger('logpoints');
    }

    private function invalidateOpcache($breakpoint)
    {
        if (!extension_loaded('Zend OPcache') || ini_get('opcache.enable') != '1') {
            return false;
        }

        return opcache_invalidate($this->sourceRoot . DIRECTORY_SEPARATOR . $breakpoint->location()->path(), true);
    }
}
