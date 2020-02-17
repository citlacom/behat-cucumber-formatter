<?php

namespace Vanare\BehatCucumberJsonFormatter\Formatter;

use Behat\Behat\EventDispatcher\Event as BehatEvent;
use Behat\Behat\Tester\Result;
use Behat\Testwork\Counter\Timer;
use Behat\Testwork\EventDispatcher\Event as TestworkEvent;
use Behat\Testwork\Output\Printer\OutputPrinter;
use Behat\Testwork\Tester\Result\TestResult;
use Behat\Testwork\Tester\Result\TestResults;
use Vanare\BehatCucumberJsonFormatter\Node;
use Vanare\BehatCucumberJsonFormatter\Printer\FileOutputPrinter;
use Vanare\BehatCucumberJsonFormatter\Renderer\JsonRenderer;
use Vanare\BehatCucumberJsonFormatter\Renderer\RendererInterface;

class Formatter implements FormatterInterface
{
    /** @var array */
    private $parameters;

    /** @var Timer */
    private $timer;

    /** @var OutputPrinter */
    private $printer;

    /** @var RendererInterface */
    private $renderer;

    /** @var Node\Suite[] */
    private $suites;

    /** @var Node\Suite */
    private $currentSuite;

    /** @var int */
    private $featureCounter = 1;

    /** @var Node\Feature */
    private $currentFeature;

    /** @var Node\Scenario */
    private $currentScenario;

    /** @var bool */
    private $ignoreUndefined;

    /** @var bool */
    private $ignoreSkipped;

    /**
     * @param string $fileNamePrefix
     * @param string $outputDir
     */
    public function __construct($fileNamePrefix, $outputDir)
    {
        $this->renderer = new JsonRenderer($this);
        $this->printer = new FileOutputPrinter($fileNamePrefix, $outputDir);
        $this->timer = new Timer();
    }

    /** @inheritdoc */
    public static function getSubscribedEvents()
    {
        return [
            TestworkEvent\ExerciseCompleted::BEFORE => 'onBeforeExercise',
            TestworkEvent\ExerciseCompleted::AFTER => 'onAfterExercise',
            TestworkEvent\SuiteTested::BEFORE => 'onBeforeSuiteTested',
            TestworkEvent\SuiteTested::AFTER => 'onAfterSuiteTested',
            BehatEvent\FeatureTested::BEFORE => 'onBeforeFeatureTested',
            BehatEvent\FeatureTested::AFTER => 'onAfterFeatureTested',
            BehatEvent\ScenarioTested::BEFORE => 'onBeforeScenarioTested',
            BehatEvent\ScenarioTested::AFTER => 'onAfterScenarioTested',
            BehatEvent\OutlineTested::BEFORE => 'onBeforeOutlineTested',
            BehatEvent\OutlineTested::AFTER => 'onAfterOutlineTested',
            BehatEvent\StepTested::BEFORE => 'onBeforeStepTested',
            BehatEvent\StepTested::AFTER => 'onAfterStepTested',
        ];
    }

    /** @inheritdoc */
    public function setFileName($fileName) {
        $this->printer->setResultFileName($fileName);
    }

    /** @inheritdoc */
    public function setIgnoreUndefined(bool $ignore) {
        $this->ignoreUndefined = $ignore;
    }

    /** @inheritdoc */
    public function setIgnoreSkipped(bool $ignore) {
        $this->ignoreSkipped = $ignore;
    }

    /** @inheritdoc */
    public function getDescription()
    {
        return 'Cucumber style formatter';
    }

    /** @inheritdoc */
    public function getOutputPrinter()
    {
        return $this->printer;
    }

    /** @inheritdoc */
    public function setParameter($name, $value)
    {
        $this->parameters[$name] = $value;
    }

    /** @inheritdoc */
    public function getParameter($name)
    {
        return $this->parameters[$name];
    }

    /** @inheritdoc */
    public function getSuites()
    {
        return $this->suites;
    }

    /**
     * Triggers before running tests.
     *
     * @param TestworkEvent\BeforeExerciseCompleted $event
     */
    public function onBeforeExercise(TestworkEvent\BeforeExerciseCompleted $event)
    {
        $this->timer->start();
    }

    /**
     * Triggers after running tests.
     *
     * @param TestworkEvent\ExerciseCompleted $event
     */
    public function onAfterExercise(TestworkEvent\ExerciseCompleted $event)
    {
        $this->timer->stop();

        $this->renderer->render();
        if (!$this->printer->getResultFileName()) {
            $this->printer->setResultFileName(
                str_replace(
                    DIRECTORY_SEPARATOR,
                    FileOutputPrinter::FILE_SEPARATOR,
                    $this->currentFeature->getFilenameForReport()
                )
            );
        }

        $this->printer->write($this->renderer->getResult());
    }

    /**
     * @param TestworkEvent\BeforeSuiteTested $event
     */
    public function onBeforeSuiteTested(TestworkEvent\BeforeSuiteTested $event)
    {
        $this->currentSuite = new Node\Suite();
        $this->currentSuite->setName($event->getSuite()->getName());
    }

    /**
     * @param TestworkEvent\SuiteTested $event
     */
    public function onAfterSuiteTested(TestworkEvent\SuiteTested $event)
    {
        $this->suites[] = $this->currentSuite;
    }

    /**
     * @param BehatEvent\BeforeFeatureTested $event
     */
    public function onBeforeFeatureTested(BehatEvent\BeforeFeatureTested $event)
    {
        $feature = new Node\Feature();
        $feature->setId($this->featureCounter);
        ++$this->featureCounter;
        $feature->setName($event->getFeature()->getTitle());
        $feature->setDescription($event->getFeature()->getDescription());
        $feature->setTags($event->getFeature()->getTags());
        $feature->setFile($event->getFeature()->getFile());
        $feature->setLine($event->getFeature()->getLine());
        $feature->setKeyword($event->getFeature()->getKeyword());
        $this->currentFeature = $feature;
    }

    /**
     * @param BehatEvent\AfterFeatureTested $event
     */
    public function onAfterFeatureTested(BehatEvent\AfterFeatureTested $event)
    {
        $this->currentSuite->addFeature($this->currentFeature);
    }

    /**
     * @param BehatEvent\BeforeScenarioTested $event
     */
    public function onBeforeScenarioTested(BehatEvent\BeforeScenarioTested $event)
    {
        $fullTitle = explode("\n", $event->getScenario()->getTitle());
        if (count($fullTitle) > 1) {
            $title = array_shift($fullTitle);
        } else {
            $title = implode("\n", $fullTitle);
        }
        $description = implode("\n", $fullTitle);

        $scenario = new Node\Scenario();
        $scenario->setName($title);
        $scenario->setDescription($description);
        $scenario->setTags($event->getScenario()->getTags());
        $scenario->setLine($event->getScenario()->getLine());
        $scenario->setType($event->getScenario()->getNodeType());
        $scenario->setKeyword($event->getScenario()->getKeyword());
        $scenario->setFeature($this->currentFeature);
        $this->currentScenario = $scenario;
    }

    /**
     * @param BehatEvent\AfterScenarioTested $event
     */
    public function onAfterScenarioTested(BehatEvent\AfterScenarioTested $event)
    {
        $scenarioPassed = $event->getTestResult()->isPassed();

        if ($scenarioPassed) {
            $this->currentFeature->addPassedScenario();
        } else {
            $this->currentFeature->addFailedScenario();
        }

        $this->currentScenario->setPassed($event->getTestResult()->isPassed());
        $this->currentFeature->addScenario($this->currentScenario);
    }

    /**
     * @param BehatEvent\BeforeOutlineTested $event
     */
    public function onBeforeOutlineTested(BehatEvent\BeforeOutlineTested $event)
    {
        $scenario = new Node\Scenario();
        $scenario->setName($event->getOutline()->getTitle());
        $scenario->setTags($event->getOutline()->getTags());
        $scenario->setLine($event->getOutline()->getLine());
        $scenario->setType('scenario_outline');
        $scenario->setKeyword($event->getOutline()->getKeyword());
        $scenario->setFeature($this->currentFeature);
        $this->currentScenario = $scenario;
    }

    /**
     * @param BehatEvent\AfterOutlineTested $event
     */
    public function onAfterOutlineTested(BehatEvent\AfterOutlineTested $event)
    {
        /** @var TestResults $testResults */
        $testResults = $event->getTestResult();
        $stepCount = count($event->getOutline()->getSteps());
        foreach ($testResults as $i => $testResult) {
            $example = clone $this->currentScenario;

            // use correct line number of example row
            $line = $event->getOutline()->getExampleTable()->getRowLine($i + 1);
            $example->setLine($line);

            // remove all steps and attach only steps for that example row
            $steps = array_slice($example->getSteps(), $i * $stepCount, $stepCount);
            $example->setSteps($steps);

            $scenarioPassed = $testResult->isPassed();

            if ($scenarioPassed) {
                $this->currentFeature->addPassedScenario();
            } else {
                $this->currentFeature->addFailedScenario();
            }

            $example->setPassed($scenarioPassed);
            $this->currentFeature->addScenario($example);
        }
    }

    /**
     * @param BehatEvent\BeforeStepTested $event
     */
    public function onBeforeStepTested(BehatEvent\BeforeStepTested $event)
    {
        $this->timer->start();
    }

    /**
     * @param BehatEvent\AfterStepTested $event
     */
    public function onAfterStepTested(BehatEvent\AfterStepTested $event)
    {
        $this->timer->stop();

        /** @var Result\ExecutedStepResult $result */
        $result = $event->getTestResult();

        if ($this->ignoreUndefined && $result instanceof Result\UndefinedStepResult) {
            return;
        }

        if ($this->ignoreSkipped && $result instanceof Result\SkippedStepResult) {
            return;
        }

        $step = new Node\Step();
        $step->setKeyword($event->getStep()->getKeyword());
        $step->setName($event->getStep()->getText());
        $step->setLine($event->getStep()->getLine());
        $step->setArguments($event->getStep()->getArguments());
        $step->setResult($result);
        $step->setResultCode($result->getResultCode());
        $step->setDuration($this->timer->getSeconds());

        $arguments = [];
        $location = 'Undefined';
        if (!$result instanceof Result\UndefinedStepResult) {
            $location = $result->getStepDefinition()->getPath();
            foreach ($result->getSearchResult()->getMatchedArguments() as $argument) {
                $a = new \stdClass();
                $a->val = (string) $argument;
                $arguments[] = $a;
            }
        }

        $match = ['location' => $location];
        if ($arguments) {
            $match['arguments'] = $arguments;
        }

        $step->setMatch($match);

        $this->processStep($step, $result);

        $this->currentScenario->addStep($step);
    }

    /** @inheritdoc */
    public function getName()
    {
        return 'cucumber_json';
    }

    /**
     * @param Node\Step  $step
     * @param TestResult $result
     */
    protected function processStep(Node\Step $step, TestResult $result)
    {
        // Pended
        if (is_a($result, Result\UndefinedStepResult::class)) {
            return;
        }

        // Skipped
        if (is_a($result, Result\SkippedStepResult::class)) {
            /** @var Result\SkippedStepResult $result */
            $step->setDefinition($result->getStepDefinition());

            return;
        }

        // Failed or passed
        if (is_a($result, Result\ExecutedStepResult::class)) {
            /** @var Result\ExecutedStepResult $result */
            $step->setDefinition($result->getStepDefinition());
            $exception = $result->getException();
            if ($exception) {
                $step->setException($exception->getMessage());
            }

            return;
        }
    }
}
