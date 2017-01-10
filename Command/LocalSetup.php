<?php

namespace Dorgflow\Command;

class LocalSetup extends CommandBase {

  function XXXX__construct($git_status, $waypoint_manager_branches, $waypoint_manager_patches) {
    $this->waypoint_manager_branches = $waypoint_manager_branches;
    $this->waypoint_manager_patches = $waypoint_manager_patches;
    $this->git_status = $git_status;
  }

  public function execute() {
    // TEMPORARY: get services from the container.
    // @todo inject these.
    $this->git_info = $this->container->get('git.info');
    $this->waypoint_manager_branches = $this->container->get('waypoint_manager.branches');
    $this->waypoint_manager_patches = $this->container->get('waypoint_manager.patches');

    // Check git is clean.
    $clean = $this->git_info->gitIsClean();
    if (!$clean) {
      throw new \Exception("Git repository is not clean. Aborting.");
    }

    // Create branches.
    $master_branch = $this->waypoint_manager_branches->getMasterBranch();

    // If the master branch is not current, abort.
    if (!$master_branch->isCurrentBranch()) {
      print strtr("Detected master branch !branch, but it is not the current branch. Aborting.\n", [
        '!branch' => $master_branch->getBranchName(),
      ]);
      exit();
    }

    print strtr("Detected master branch !branch.\n", [
      '!branch' => $master_branch->getBranchName(),
    ]);

    $feature_branch = $this->waypoint_manager_branches->getFeatureBranch();

    // Check whether feature branch exists.
    if ($feature_branch->exists()) {
      throw new \Exception("The feature branch already exists. Use the update command. Aborting.");
    }
    else {
      // If feature branch doens't exist, create it in git.
      // Check we are on the master branch -- if not, throw exception.
      if (!$master_branch->isCurrentBranch()) {
        throw new \Exception("The master branch is not current. Aborting");
      }

      $feature_branch->gitCreate();

      print strtr("Created feature branch !branch.\n", [
        '!branch' => $feature_branch->getBranchName(),
      ]);
    }

    // Get the patches and create them.
    $patches = $this->waypoint_manager_patches->setUpPatches();

    // If no patches, we're done.
    if (empty($patches)) {
      print "There are no patches to apply.\n";
      return;
    }

    // Output the patches.
    foreach ($patches as $patch) {
      $patch_committed = $patch->commitPatch();

      // Message.
      if ($patch_committed) {
        print strtr("Applied and committed patch !patchname.\n", [
          '!patchname' => $patch->getPatchFilename(),
        ]);
      }
      else {
        print strtr("Patch !patchname did not apply.\n", [
          '!patchname' => $patch->getPatchFilename(),
        ]);
      }
    }

    // If final patch didn't apply, then output a message: the latest patch
    // has rotted. Save the patch file to disk and give the filename in the
    // message.
    if (!$patch_committed) {
      // Save the file so the user can apply it manually.
      file_put_contents($patch->getPatchFilename(), $patch->getPatchFile());

      print strtr("The most recent patch, !patchname, did not apply. You should attempt to apply it manually. The patch file has been saved to the working directory.\n", [
        '!patchname' => $patch->getPatchFilename(),
      ]);
    }
  }

}
