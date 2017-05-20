<?php

namespace Dorgflow\Command;

use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Deletes the current feature branch.
 */
class Cleanup extends CommandBase {

  /**
   * {@inheritdoc}
   */
  protected function configure() {
    $this
      ->setName('cleanup')
      ->setDescription('Deletes the current feature branch.')
      ->setHelp('Deletes the current feature branch.');
  }

  /**
   * Creates an instance of this command, injecting services from the container.
   */
  static public function create(ContainerBuilder $container) {
    return new static(
      $container->get('git.info'),
      $container->get('waypoint_manager.branches')
    );
  }

  function __construct($git_info, $waypoint_manager_branches) {
    $this->git_info = $git_info;
    $this->waypoint_manager_branches = $waypoint_manager_branches;
  }

  public function execute() {
    // Check git is clean.
    $clean = $this->git_info->gitIsClean();
    if (!$clean) {
      throw new \Exception("Git repository is not clean. Aborting.");
    }

    $master_branch = $this->waypoint_manager_branches->getMasterBranch();
    $feature_branch = $this->waypoint_manager_branches->getFeatureBranch();

    $master_branch_name = $master_branch->getBranchName();
    $feature_branch_name = $feature_branch->getBranchName();

    print "You are about to checkout branch $master_branch_name and DELETE branch $feature_branch_name!\n";
    $confirmation = readline("Please enter 'delete' to confirm:");

    if ($confirmation != 'delete') {
      print "Clean up aborted.\n";
      return;
    }

    $master_branch_name = $master_branch->getBranchName();
    shell_exec("git checkout $master_branch_name");

    shell_exec("git branch -D $feature_branch_name");

    // TODO: delete any patch files for this issue.
  }

}
