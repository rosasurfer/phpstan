<?php declare(strict_types = 1);

namespace PHPStan\File;

class FileExcluder
{

	/**
	 * Directories to exclude from analysing
	 *
	 * @var string[]
	 */
	private $analyseExcludes;

	public function __construct(
		FileHelper $fileHelper,
		array $analyseExcludes
	)
	{
		$this->analyseExcludes = array_map(function (string $exclude) use ($fileHelper): string {
			$trailingDirSeparator = ($len=strLen($exclude)) && in_array($exclude[$len-1], ['\\', '/']);

		    $normalized = $fileHelper->normalizePath($exclude);

		    if ($trailingDirSeparator) {
		        $normalized .= DIRECTORY_SEPARATOR;
		    }

			if ($this->isFnmatchPattern($normalized)) {
				return $normalized;
			}

			return $fileHelper->absolutizePath($normalized);
		}, $analyseExcludes);
	}

	public function isExcludedFromAnalysing(string $file): bool
	{
		foreach ($this->analyseExcludes as $exclude) {
			if (strpos($file, $exclude) === 0) {
				return true;
			}

			$isWindows = DIRECTORY_SEPARATOR === '\\';
			if ($isWindows) {
				$fnmatchFlags = FNM_NOESCAPE | FNM_CASEFOLD;
			} else {
				$fnmatchFlags = 0;
			}

			if ($this->isFnmatchPattern($exclude) && fnmatch($exclude, $file, $fnmatchFlags)) {
				return true;
			}
		}

		return false;
	}

	private function isFnmatchPattern(string $path): bool
	{
		return preg_match('~[*?[\]]~', $path) > 0;
	}

}
