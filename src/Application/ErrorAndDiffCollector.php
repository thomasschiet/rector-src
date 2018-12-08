<?php declare(strict_types=1);

namespace Rector\Application;

use PHPStan\AnalysedCodeException;
use Rector\ConsoleDiffer\DifferAndFormatter;
use Rector\Error\ExceptionCorrector;
use Rector\NodeTypeResolver\FileSystem\CurrentFileInfoProvider;
use Rector\Reporting\FileDiff;
use Symplify\PackageBuilder\FileSystem\SmartFileInfo;
use Throwable;

final class ErrorAndDiffCollector
{
    /**
     * @var Error[]
     */
    private $errors = [];

    /**
     * @var CurrentFileInfoProvider
     */
    private $currentFileInfoProvider;

    /**
     * @var FileDiff[]
     */
    private $fileDiffs = [];

    /**
     * @var DifferAndFormatter
     */
    private $differAndFormatter;

    /**
     * @var AppliedRectorCollector
     */
    private $appliedRectorCollector;

    /**
     * @var ExceptionCorrector
     */
    private $exceptionCorrector;

    public function __construct(
        CurrentFileInfoProvider $currentFileInfoProvider,
        DifferAndFormatter $differAndFormatter,
        AppliedRectorCollector $appliedRectorCollector,
        ExceptionCorrector $exceptionCorrector
    ) {
        $this->currentFileInfoProvider = $currentFileInfoProvider;
        $this->differAndFormatter = $differAndFormatter;
        $this->appliedRectorCollector = $appliedRectorCollector;
        $this->exceptionCorrector = $exceptionCorrector;
    }

    public function addError(Error $error): void
    {
        $this->errors[] = $error;
    }

    /**
     * @return Error[]
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    public function addErrorWithRectorMessage(string $rectorClass, string $message): void
    {
        $this->errors[] = new Error($this->currentFileInfoProvider->getSmartFileInfo(), $message, null, $rectorClass);
    }

    public function addFileDiff(
        SmartFileInfo $smartFileInfo,
        string $newContent,
        string $oldContent,
        ?string $rectorClass = null
    ): void {
        if ($newContent === $oldContent) {
            return;
        }

        $appliedRectors = $this->appliedRectorCollector->getRectorClasses() ?: $rectorClass ? [$rectorClass] : [];

        // always keep the most recent diff
        $this->fileDiffs[$smartFileInfo->getRealPath()] = new FileDiff(
            $smartFileInfo->getRealPath(),
            $this->differAndFormatter->diffAndFormat($oldContent, $newContent),
            $appliedRectors
        );
    }

    /**
     * @return FileDiff[]
     */
    public function getFileDiffs(): array
    {
        return $this->fileDiffs;
    }

    public function addAutoloadError(AnalysedCodeException $analysedCodeException, SmartFileInfo $fileInfo): void
    {
        $message = $this->exceptionCorrector->getAutoloadExceptionMessageAndAddLocation($analysedCodeException);

        $this->addError(new Error($fileInfo, $message));
    }

    public function addThrowableWithFileInfo(Throwable $throwable, SmartFileInfo $fileInfo): void
    {
        $rectorClass = $this->exceptionCorrector->matchRectorClass($throwable);
        if ($rectorClass) {
            $this->addErrorWithRectorMessage($rectorClass, $throwable->getMessage());
        } else {
            $this->addError(new Error($fileInfo, $throwable->getMessage(), $throwable->getCode()));
        }
    }
}
