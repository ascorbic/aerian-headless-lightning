<?php

use Behat\Behat\Context\Context;
use Behat\Behat\Context\SnippetAcceptingContext;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Drupal\DrupalExtension\Hook\Scope\EntityScope;
use Drupal\DrupalExtension\Context\MinkContext;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Gherkin\Node\TableNode;
use Behat\Behat\Hook\Scope\AfterStepScope;

use Drupal\DrupalExtension\Context\RawDrupalContext;

/**
 * Define application features from the specific context.
 */
class FeatureContext extends RawDrupalContext implements Context, SnippetAcceptingContext {
  /**
   * Initializes context.
   * Every scenario gets its own context object.
   *
   * @param array $parameters
   *   Context parameters (set them in behat.yml)
   */
  public function __construct(array $parameters = []) {
    // Initialize your context here
  }

  /** @var \Drupal\DrupalExtension\Context\MinkContext */
  private $minkContext;
  /** @BeforeScenario */
  public function gatherContexts(BeforeScenarioScope $scope)
  {
      $environment = $scope->getEnvironment();
      $this->minkContext = $environment->getContext('Drupal\DrupalExtension\Context\MinkContext');
  }

//
// Place your definition and hook methods here:
//
//  /**
//   * @Given I have done something with :stuff
//   */
//  public function iHaveDoneSomethingWith($stuff) {
//    doSomethingWith($stuff);
//  }
//

    /**
     * Fills in form field with specified id|name|label|value
     * Example: And I enter the value of the env var "TEST_PASSWORD" for "edit-account-pass-pass1"
     *
     * @Given I enter the value of the env var :arg1 for :arg2
     */
    public function fillFieldWithEnv($value, $field)
    {
        $this->minkContext->fillField($field, getenv($value));
    }

    /**
     * @Given I wait for the progress bar to finish
     */
    public function iWaitForTheProgressBarToFinish() {
      $this->iFollowMetaRefresh();
    }

    /**
     * @Given I follow meta refresh
     *
     * https://www.drupal.org/node/2011390
     */
    public function iFollowMetaRefresh() {
      while ($refresh = $this->getSession()->getPage()->find('css', 'meta[http-equiv="Refresh"]')) {
        $content = $refresh->getAttribute('content');
        $url = str_replace('0; URL=', '', $content);
        $this->getSession()->visit($url);
      }
    }

    /**
     * @Given I have wiped the site
     */
    public function iHaveWipedTheSite()
    {
        $site = getenv('TERMINUS_SITE');
        $env = getenv('TERMINUS_ENV');

        passthru("terminus env:wipe $site.$env --yes");
    }

    /**
     * @Given I have reinstalled
     */
    public function iHaveReinstalled()
    {
        $site = getenv('TERMINUS_SITE');
        $env = getenv('TERMINUS_ENV');
        $site_name = getenv('TEST_SITE_NAME');
        $site_mail = getenv('ADMIN_EMAIL');
        $admin_password = getenv('ADMIN_PASSWORD');

        passthru("terminus --yes drush $site.$env -- --yes site-install standard --site-name=\"$site_name\" --site-mail=\"$site_mail\" --account-name=admin --account-pass=\"$admin_password\"'");
    }

    /**
     * @Given I have run the drush command :arg1
     */
    public function iHaveRunTheDrushCommand($arg1)
    {
        $site = getenv('TERMINUS_SITE');
        $env = getenv('TERMINUS_ENV');

        $return = '';
        $output = array();
        exec("terminus drush $site.$env -- " . $arg1, $output, $return);
        // echo $return;
        // print_r($output);

    }

    /**
     * @Given I have committed my changes with comment :arg1
     */
    public function iHaveCommittedMyChangesWithComment($arg1)
    {
        $site = getenv('TERMINUS_SITE');
        $env = getenv('TERMINUS_ENV');

        passthru("terminus --yes $site.$env env:commit --message='$arg1'");
    }

    /**
     * @Given I have exported configuration
     */
    public function iHaveExportedConfiguration()
    {
        $site = getenv('TERMINUS_SITE');
        $env = getenv('TERMINUS_ENV');

        $return = '';
        $output = array();
        exec("terminus drush $site.$env -- config-export -y", $output, $return);
    }

    /**
     * Creates content of the given type.
     *
     * @Given I am viewing the node type of :type with the title :title
     */
    public function searchNodeTitleAndType($type, $title)
    {

      $sql = "SELECT n.nid FROM node_field_data n WHERE n.title = `${title}` AND n.type = `${type}`";
      //$test = "SELECT n.nid FROM node_field_data n WHERE n.title = 'Chad Parish' AND n.type = 'person';";
      var_export($sql);
      $command = 'sqlq';
      $result = $this->getDriver('drush')->$command($this->fixStepArgument($sql));
      var_export($result);

      $this->visitPath('/node/' . $result);
    }


  /**
     * @Given I wait :seconds seconds
     */
    public function iWaitSeconds($seconds)
    {
        sleep($seconds);
    }

    /**
     * @Given I wait :seconds seconds or until I see :text
     */
    public function iWaitSecondsOrUntilISee($seconds, $text)
    {
        $errorNode = $this->spin( function($context) use($text) {
            $node = $context->getSession()->getPage()->find('named', array('content', $text));
            if (!$node) {
              return false;
            }
            return $node->isVisible();
        }, $seconds);

        // Throw to signal a problem if we were passed back an error message.
        if (is_object($errorNode)) {
          throw new Exception("Error detected when waiting for '$text': " . $errorNode->getText());
        }
    }

    // http://docs.behat.org/en/v2.5/cookbook/using_spin_functions.html
    // http://mink.behat.org/en/latest/guides/traversing-pages.html#selectors
    public function spin ($lambda, $wait = 60)
    {
        for ($i = 0; $i <= $wait; $i++)
        {
            if ($i > 0) {
              sleep(1);
            }

            $debugContent = $this->getSession()->getPage()->getContent();
            file_put_contents("/tmp/mink/debug-" . $i, "\n\n\n=================================\n$debugContent\n=================================\n\n\n");

            try {
                if ($lambda($this)) {
                    return true;
                }
            } catch (Exception $e) {
                // do nothing
            }

            // If we do not see the text we are waiting for, fail fast if
            // we see a Drupal 8 error message pane on the page.
            $node = $this->getSession()->getPage()->find('named', array('content', 'Error'));
            if ($node) {
              $errorNode = $this->getSession()->getPage()->find('css', '.messages--error');
              if ($errorNode) {
                return $errorNode;
              }
              $errorNode = $this->getSession()->getPage()->find('css', 'main');
              if ($errorNode) {
                return $errorNode;
              }
              return $node;
            }
        }

        $backtrace = debug_backtrace();

        throw new Exception(
            "Timeout thrown by " . $backtrace[1]['class'] . "::" . $backtrace[1]['function'] . "()\n" .
            $backtrace[1]['file'] . ", line " . $backtrace[1]['line']
        );

        return false;
    }

    /**
     * @AfterStep
     */
    public function afterStep(AfterStepScope $scope)
    {
        // Do nothing on steps that pass
        $result = $scope->getTestResult();
        if ($result->isPassed()) {
            return;
        }

        // Otherwise, dump the page contents.
        $session = $this->getSession();
        $page = $session->getPage();
        $html = $page->getContent();
        $html = static::trimHead($html);

//        print "::::::::::::::::::::::::::::::::::::::::::::::::\n";
//        print $html . "\n";
//        print "::::::::::::::::::::::::::::::::::::::::::::::::\n";
    }

  /**
   * Before nodeCreate check field value for file, if present create file and replace with fid
   * Field should be in format 'file;__file_source__;__file_name_
   * @beforeNodeCreate
   */
  public function nodeCreateAlter(EntityScope $scope) {
    $node = $scope->getEntity();
    foreach ($node as $key => $value) {
      if (strpos($value, 'file;') !== FALSE) {
        $file_info = explode(';', $value);
        $file_source = $file_info[1];
        $file_name = $file_info[2];
        $uri = file_unmanaged_copy($file_source, "public://$file_name", FILE_EXISTS_REPLACE);
        $file = \Drupal\file\Entity\File::create(['uri' => $uri]);
        $file->save();
        $fid = $file->id();
        $node->$key = $fid;
      }
    }
  }

  /**
   * Returns fixed step argument (with \\" replaced back to ").
   *
   * @param string $argument
   *
   * @return string
   */
  protected function fixStepArgument($argument)
  {
    return str_replace('\\"', '"', $argument);
  }

  /**
   * @AfterStep
   */
  public function takeScreenshotAfterFailedStep($event)
  {
    if ($event->getTestResult()->getResultCode() === \Behat\Testwork\Tester\Result\TestResult::FAILED) {
      $driver = $this->getSession()->getDriver();
      if ($driver instanceof \Behat\Mink\Driver\Selenium2Driver) {
        $stepText = $event->getStep()->getText();
        $fileName = preg_replace('#[^a-zA-Z0-9\._-]#', '', $stepText).'.png';
        $filePath = sys_get_temp_dir();
        $this->saveScreenshot($fileName, $filePath);
        print "Screenshot for '{$stepText}' placed in ".$filePath.DIRECTORY_SEPARATOR.$fileName."\n";
      }
    }
  }

    /**
     * Remove everything in the '<head>' element except the
     * title, because it is long and uninteresting.
     */
    protected static function trimHead($html)
    {
        $html = preg_replace('#\<head\>.*\<title\>#sU', '<head><title>', $html);
        $html = preg_replace('#\</title\>.*\</head\>#sU', '</title></head>', $html);
        return $html;
    }
}
