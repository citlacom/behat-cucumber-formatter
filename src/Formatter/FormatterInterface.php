<?php

namespace Vanare\BehatCucumberJsonFormatter\Formatter;

use Behat\Testwork\Output\Formatter as FormatterOutputInterface;
use Vanare\BehatCucumberJsonFormatter\Node\Suite;

interface FormatterInterface extends FormatterOutputInterface
{
    /**
     * Returns all executed suites.
     *
     * @return Suite[]
     */
    public function getSuites();

    /**
     * Set a fixed filename, which will override the current feature filename.
     *
     * @param $fileName
     */
    public function setFileName($fileName);

    /**
     * Ignore undefined steps for reporting.
     *
     * @param bool $ignore
     * @return void
     */
    public function setIgnoreUndefined(bool $ignore);

    /**
     * Ignore skipped steps for reporting.
     *
     * @param bool $ignore
     * @return void
     */
    public function setIgnoreSkipped(bool $ignore);

}
