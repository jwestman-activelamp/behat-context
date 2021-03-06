<?php

use Behat\Behat\Context\ClosuredContextInterface,
    Behat\Behat\Context\TranslatedContextInterface,
    Behat\Behat\Context\BehatContext,
    Behat\Behat\Exception\PendingException;
use Behat\Gherkin\Node\PyStringNode,
    Behat\Gherkin\Node\TableNode;

require_once('DrupalContext.php');

/**
 * Generic Drupal Servie API Behat functionality.
 */
class DrupalServiceAPIBehatContext extends DrupalContext
{

    // Store the raw response.
    protected $apiResponse = NULL;
    // All responses should be converted into a PHP array and stored here.
    protected $apiResponseArray = NULL;
    // The type of API response (json, xml, etc...)
    protected $apiResponseType = NULL;

    /**
     * Initializes context.
     * Every scenario gets it's own context object.
     *
     * @param array $parameters context parameters (set them up through behat.yml)
     */
    public function __construct(array $parameters)
    {
        parent::__construct($parameters);
    }

    /**
     * Return the value of a property.
     *
     * @param string $property_string
     *   A property name or path to the property. Path the property can be
     *   constructed with forward slashes '/' as the delimiters. Property
     *   names may contain regular expression matches.
     * @param bool $regex
     *   (optional) defaults to TRUE. Allow regular expression matching.
     *
     * @return mixed|null
     *   The value of the property or NULL if no property found or property
     *   contains no value.
     */
    private function getProperty($property_string, $regex = TRUE) {
        $value = NULL;
        $properties = explode('/', $property_string);
        $response = $this->apiResponseArray;

        while($property = array_shift($properties)) {
            $property = ($regex) ? "^{$property}$" : preg_quote($property);
            $value = NULL;
            $keys = array_keys(get_object_vars($response));
            foreach($keys as $key) {
                if (preg_match("/{$property}/", $key)) {
                    $response = $response->$key;
                    $value = $response;
                    break;
                }
            }
        }

        return $value;
    }

    /**
     * Determine if a property exists or not.
     *
     * @param string $property_string
     *   A property name or path to the property. Path the property can be
     *   constructed with forward slashes '/' as the delimiters. Property
     *   names may contain regular expression matches.
     * @param bool $regex
     *   (optional) defaults to TRUE. Allow regular expression matching.
     *
     * @return bool
     *   TRUE if the property exists, FALSE if it does not.
     */
    private function propertyExists($property_string, $regex = TRUE) {
        $exists = FALSE;
        $properties = explode('/', $property_string);
        $response = $this->apiResponseArray;

        while($property = array_shift($properties)) {
            $exists = FALSE;
            $property = ($regex) ? "^{$property}$" : preg_quote($property);
            $keys = array_keys(get_object_vars($response));
            foreach($keys as $key) {
                if (preg_match("/{$property}/", $key)) {
                    $response = $response->$key;
                    $exists = TRUE;
                    break;
                }
            }
        }

        return $exists;
    }

    /**
     * @Given /^I call "([^"]*)" as "([^"]*)"$/
     *
     * @param string $path
     *   The relative url path to access.
     * @param string $format
     *   The format that the API should return the response in. Only 'json' is
     *   currently supported.
     * @param string $append
     *   Any string that should be appended to the GET request.
     */
    public function iCallAs($path, $format, $append = '')
    {
        // @todo probably want to use CURL so we can examine response headers.
        $url = $this->parameters['base_url'] . $path . ".{$format}{$append}";
 
        $this->apiResponse = file_get_contents($url);
        if (!strlen($this->apiResponse)) {
            throw new Exception("Could not open $path");
        }
        if ($format == 'json') {
            $this->apiResponseType = 'json';
            $this->apiResponseArray = json_decode($this->apiResponse);
        }
    }

    /**
     * @Given /^I call "([^"]*)" as "([^"]*)" with "([^"]*)"$/
     *
     * @param string $path
     *   The relative url path to access.
     * @param string $format
     *   The format that the API should return the response in. Only 'json' is
     *   currently supported.
     * @param string $append
     *   Any string that should be appended to the GET request.
     */
    public function iCallAsWith($path, $format, $append)
    {
        $this->iCallAs($path, $format, $append);
    }

    /**
     * @Given /^I call parameter "([^"]*)" as "([^"]*)"$/
     *
     * @param string $parameter_string
     *   Property name or path to parameter as located in the YML config
     *   file beneath the 'parameters' value.
     * @param string $format
     *   The format that the API should return the response in. Only 'json' is
     *   currently supported.
     */
    public function iCallParameterAs($parameter_string, $format)
    {
        $parameter_value = $this->getParameter($parameter_string);
        if ($parameter_value === NULL) {
            throw new Exception("Missing config parameter: {$parameter_string}");
        }
        $this->iCallAs($parameter_value, $format);
    }

    /**
     * @Then /^property "([^"]*)" should exist$/
     *
     * @param string $property_string
     *   A property name or path to the property. Path the property can be
     *   constructed with forward slashes '/' as the delimiters.
     */
    public function propertyShouldExist($property_string)
    {
        if (!$this->propertyExists($property_string)) {
            throw new Exception("Property {$property_string} does not exist");
        }
    }

    /**
     * @Then /^property "([^"]*)" should be "([^"]*)"$/
     *
     * @param string $property_string
     *   A property name or path to the property. Path the property can be
     *   constructed with forward slashes '/' as the delimiters.
     * @param mixed $value
     *   The value the property must equal.
     */
    public function propertyShouldBe($property_string, $value)
    {
        $property_value = $this->getProperty($property_string);
        if ($property_value === NULL) {
            throw new Exception("Missing property: {$property_string}");
        }
        if ($property_value != $value) {
            throw new Exception("Wrong value found for {$property_string}: {$property_value}");
        }
    }

    /**
     * @Then /^property "([^"]*)" should be parameter "([^"]*)"$/
     *
     * @param string $property_string
     *   A property name or path to the property. Path the property can be
     *   constructed with forward slashes '/' as the delimiters.
     *
     * @param string $config
     *   Property name or path to property as located in the YML config
     *   file beneath the 'parameters' value.
     */
    public function propertyShouldBeParameter($property_string, $parameter_string)
    {
        // Get value from config file.
        $parameter_value = $this->getParameter($parameter_string);
        if ($parameter_value === NULL) {
            throw new Exception("Missing config parameter: {$parameter_string}");
        }
        $property_value = $this->getProperty($property_string);
        if ($property_value === NULL) {
            throw new Exception("Missing api property: {$property_string}");
        }
        if ($property_value != $parameter_value) {
            throw new Exception("Wrong value found for {$property_string}: {$property_value}, wanted: {$parameter_value}");
        }
        $this->override_text = "property \"{$property_string}\" should be \"{$parameter_value}\"";
    }

    /**
     * @Then /^property "([^"]*)" should contain "([^"]*)"$/
     *
     * @param string $property_string
     *   A property name or path to the property. Path the property can be
     *   constructed with forward slashes '/' as the delimiters.
     * @param mixed $value
     *   The value the property must contain.
     */
    public function propertyShouldContain($property_string, $value)
    {
        $property_value = $this->getProperty($property_string);
        if ($property_value === NULL) {
            throw new Exception("Missing property: {$property_string}");
        }
        if (!strstr($property_value, $value)) {
            throw new Exception("Missing value ({$value}) inside {$property_string}: {$property_value}");
        }
    }

    /**
     * @Then /^property "([^"]*)" should be of type "([^"]*)"$/
     *
     * @param string $property_string
     *   A property name or path to the property. Path the property can be
     *   constructed with forward slashes '/' as the delimiters.
     * @param string $type
     *   The data type of the property. Can be 'int', 'string' or 'array'.
     */
    public function propertyShouldBeOfType($property_string, $type)
    {
        $property_value = $this->getProperty($property_string);
        if ($property_value === NULL) {
            throw new Exception("Missing property: {$property_string}");
        }
        $property_type = gettype($property_value);
        // Properties that are objects should qualify as arrays.
        if ($type == 'array') {
            $type = 'object';
        }
        // Strings that are numbers should qualify as integers.
        if ($type == 'int' && is_numeric($property_value)) {
            $type = 'string';
        }

        if ($type != $property_type) {
            throw new Exception("Wrong property type found for {$property_string}: {$property_type}");
        }
    }

    /**
     * @Then /^property "([^"]*)" should have "([^"]*)" children$/
     *
     * @param string $property_string
     *   A property name or path to the property. Path the property can be
     *   constructed with forward slashes '/' as the delimiters.
     * @param int $number
     *   Number of array elements the property should have.
     */
    public function propertyShouldHaveChildren($property_string, $number)
    {
        $property_value = $this->getProperty($property_string);
        $property_count = count((array) $property_value);
        if ($property_count != $number) {
            throw new Exception("Wrong number of elements found for {$property_string}: {$property_count}");
        }
    }    

    /**
     * @Then /^property "([^"]*)" should have at least "([^"]*)" children$/
     *
     * @param string $property_string
     *   A property name or path to the property. Path the property can be
     *   constructed with forward slashes '/' as the delimiters.
     * @param int $number
     *   Number of array elements the property should have at least.
     */
    public function propertyShouldHaveAtLeastChildren($property_string, $number)
    {
        $property_value = $this->getProperty($property_string);
        $property_count = count((array) $property_value);
        if ($property_count < $number) {
            throw new Exception("Wrong number of elements found for {$property_string}: {$property_count}");
        }
    }

    /**
     * @Then /^property "([^"]*)" should have less than "([^"]*)" children$/
     *
     * @param string $property_string
     *   A property name or path to the property. Path the property can be
     *   constructed with forward slashes '/' as the delimiters.
     * @param int $number
     *   Number of array elements the property should have less than.
     */
    public function propertyShouldHaveLessThanChildren($property_string, $number)
    {
        $property_value = $this->getProperty($property_string);
        $property_count = count((array) $property_value);
        if ($property_count >= $number) {
            throw new Exception("Wrong number of elements found for {$property_string}: {$property_count}");
        }
    }

}
