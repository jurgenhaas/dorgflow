<?php

namespace Dorgflow\Command;

class LocalUpdate extends CommandBase {

  public function execute() {
    // TEMPORARY: get services from the container.
    // @todo inject these.
    $this->git_info = $this->container->get('git.info');
    $this->waypoint_manager_branches = $this->container->get('waypoint_manager.branches');
    $this->waypoint_manager_patches = $this->container->get('waypoint_manager.patches');
    $this->git_executor = $this->container->get('git.executor');

    // Check git is clean.
    $clean = $this->git_info->gitIsClean();
    if (!$clean) {
      throw new \Exception("Git repository is not clean. Aborting.");
    }

    // Create branches.
    $master_branch = $this->waypoint_manager_branches->getMasterBranch();
    $feature_branch = $this->waypoint_manager_branches->getFeatureBranch();

    // If the feature branch is not current, abort.
    if (!$feature_branch->exists()) {
      print "Could not find a feature branch. Aborting.";
      exit();
    }
    if (!$feature_branch->isCurrentBranch()) {
      print strtr("Detected feature branch !branch, but it is not the current branch. Aborting.", [
        '!branch' => $feature_branch->getBranchName(),
      ]);
      exit();
    }

    // Get the patches and create them.
    $patches = $this->waypoint_manager_patches->setUpPatches();
    //dump($patches);

    // If no patches, we're done.
    if (empty($patches)) {
      print "No patches to apply.\n";
      return;
    }

    $patches_uncommitted = [];
    $last_committed_patch = NULL;

    // Find the first new, uncommitted patch.
    foreach ($patches as $patch) {
      if ($patch->hasCommit()) {
        // Keep updating this, so the last time it's set gives us the last
        // committed patch.
        $last_committed_patch = $patch;
      }
      else {
        $patches_uncommitted[] = $patch;
      }
    }

    // If no uncommitted patches, we're done.
    if (empty($patches_uncommitted)) {
      print "No patches to apply; existing patches are already applied to this feature branch.\n";
      return;
    }

    // If the feature branch's SHA is not the same as the last committed patch
    // SHA, then that means there are local commits on the branch that are
    // newer than the patch.
    // @todo: bug: if the tip if MY patch (ie empty dorgflow commit), then this
    // is triggering incorrectly!!
    if ($last_committed_patch->getSHA() != $feature_branch->getSHA()) {
      // Create a new branch at the tip of the feature branch.
      $forked_branch_name = $feature_branch->createForkBranchName();
      $this->git_executor->createNewBranch($forked_branch_name);

      // Reposition the FeatureBranch tip to the last committed patch.
      $this->git_executor->moveBranch($feature_branch->getBranchName(), $last_committed_patch->getSHA());

      print strtr("Moved your work at the tip of the feature branch to new branch !forkedbranchname. You should manually merge this into the feature branch to preserve your work.\n", [
        '!forkedbranchname' => $forked_branch_name,
      ]);

      // We're now ready to apply the patches.
    }

    // Output the patches.
    $patches_committed = [];
    foreach ($patches_uncommitted as $patch) {
      // Commit the patch.
      $patch_committed = $patch->commitPatch();

      // Message.
      if ($patch_committed) {
        // Keep a list of the patches that we commit.
        $patches_committed[] = $patch;

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

    // If all the patches were already committed, we're done.
    if (empty($patches_committed)) {
      print "No new patches to apply.\n";
      return;
    }

    // If final patch didn't apply, then output a message: the latest patch
    // has rotted. Save the patch file to disk and give the filename in the
    // message.
    if (!$patch_committed) {
      // Save the file so the user can apply it manually.
      file_put_contents($patch->getPatchFilename(), $patch->getPatchFile());

      print strtr("The most recent patch, !patchname, did not apply. You should attempt to apply it manually. "
        . "The patch file has been saved to the working directory.\n", [
        '!patchname' => $patch->getPatchFilename(),
      ]);
    }
  }

}
