<?php
namespace EcgM2\Sniffs\Plugins;

use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Files\File;

class PluginSniff implements Sniff
{
    /**
     * @var int PARAMS_QTY Minimum allowed parameters in the plug
     */
    const PARAMS_QTY = 2;

    /**
     * @var string P_BEFORE prefix for the before methods
     */
    const P_BEFORE = 'before';

    /**
     * @var string P_AROUND prefix for the around methods
     */
    const P_AROUND = 'around';

    /**
     * @var string P_AFTER prefix for the after methods
     */
    const P_AFTER = 'after';

    /**
     * @var string P_DIRECTORY Directory where plugins live, dictated by
     *                         Magento 2 standards
     */
    const P_DIRECTORY = '/Plugin/';

    /**
     * @var array $prefixes Collection of the prefixes for the three valid
     *                      Magento 2 interceptor methods
     */
    protected $prefixes = [
        self::P_BEFORE,
        self::P_AROUND,
        self::P_AFTER
    ];

    /**
     * @var array $exclude
     */
    protected $exclude = [];

    /**
     * register
     * @return array T_FUNCTION
     */
    public function register()
    {
        return [T_FUNCTION];
    }

    /**
     *  Called when this token type has been found by PHP_CodeSniffer\Sniffs\Sniff.
     *  This is the code entry point for this class.
     *
     * The stackPtr variable indicates where in the stack the token was found.
     * A sniff can acquire information this token, along with all the other
     * tokens within the stack by first acquiring the token stack:
     *
     * @param PHP_CodeSniffer\Files\File $phpcsFile
     * @param int $stackPtr The position in the PHP_CodeSniffer file's token stack where
     *                      the token was found.
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        // Only target methods in the plugin directory 
        if(!strpos($phpcsFile->path, self::P_DIRECTORY)) {
            return;
        }

        $functionName = $phpcsFile->getDeclarationName($stackPtr);

        $this->phpcsFile = $phpcsFile;
        $this->stackPtr  = $stackPtr;

        $pluginPreFix = $this->startsWith($functionName, $this->prefixes, $this->exclude);

        if ($pluginPreFix) {
            $params    = $phpcsFile->getMethodParameters($stackPtr);
            $paramsQty = count($params);

            $this->checkParamsQuantity($functionName, $paramsQty);

            if ($pluginPreFix == self::P_AROUND) {
                $this->checkAroundForProceed($functionName, $params);
            }

            if ($pluginPreFix == self::P_BEFORE) {
                return;
            }

            $tokens = $phpcsFile->getTokens();

            $hasReturn = false;
            foreach ($tokens as $currToken) {
                if ($currToken['code'] == T_RETURN && isset($currToken['conditions'][$stackPtr])) {
                    $hasReturn = true;
                    break;
                }
            }

            if (!$hasReturn) {
                $phpcsFile->addError(
                    'Plugin ' . $functionName . ' function must return value.',
                    $stackPtr,
                    'PluginError'
                );
            }
        }
    }

    /**
     * Check to make sure the plugin method if a valid intercepter. If it begins with
     * one of the allowed prefixes, before, around, and after, it is valid.
     * @param string $haystack
     * @param array $needle
     * @param array $excludeFunctions
     * @return false | string Returns the prefix of the plugin, will only be one of the
     *                        prefixes that were defined in the constants:
     *                            - before
     *                            - after
     *                            - around
     */
    protected function startsWith($haystack, array $needle, array $excludeFunctions = array())
    {
        if (in_array($haystack, $excludeFunctions)) {
            return false;
        }
        $haystackLength = strlen($haystack);
        foreach ($needle as $currPref) {
            $length = strlen($currPref);
            if ($haystackLength != $length && substr($haystack, 0, $length) === $currPref) {
                return $currPref;
            }
        }
        return false;
    }

    /**
     * Throws an error if the two required parameters are not included in the plugin
     * @param type $functionName Name of the plugin, for reporting purposes
     * @param type $paramsQty How many paramteres are inlucded
     * @return void
     */
    protected function checkParamsQuantity($functionName, $paramsQty)
    {
        if ($paramsQty < self::PARAMS_QTY) {
            $this->phpcsFile->addError(
                'Plugin ' . $functionName . ' function should have at least two parameters.',
                $this->stackPtr,
                'PluginError'
            );
        }
    }

    /**
     * Throws an error if the "proceed" paramter is not included
     *      - omitting the call to $proceed() can alter major functionality
     * @param type $functionName Name of the plugin, for reporting purposes
     * @param array $params Nested array of the parameters with all data included
     * @return void
     */
    protected function checkAroundForProceed($functionName, $params)
    {
        foreach ($params as $param) {
            if($param['content'] === 'callable $proceed') {
                return;
            }
        }

        $this->phpcsFile->addError(
            'Plugin ' . $functionName . ' is an around function and must call a callable $proceed',
            $this->stackPtr,
            'PluginError'
        );
    }
}
