<?php

namespace Dorgflow\Tests;

use Symfony\Component\DependencyInjection\Reference;

/**
 * System test for the create patch command.
 *
 * This mocks raw input, that is, git info, git branches, and drupal.org data.
 *
 * Run with:
 * @code
 *   vendor/bin/phpunit tests/CommandCreatePatchTest.php
 * @endcode
 */
class CommandCreatePatchTest extends CommandTestBase {

  /**
   * Test the command bails when git is not clean.
   */
  public function testGitUnclean() {
    $container = new \Symfony\Component\DependencyInjection\ContainerBuilder();

    $git_info = $this->getMockBuilder(\Dorgflow\Service\GitInfo::class)
      ->disableOriginalConstructor()
      ->setMethods(['gitIsClean'])
      ->getMock();
    $git_info->method('gitIsClean')
      ->willReturn(FALSE);

    $container->set('git.info', $git_info);
    // These won't get called, so don't need to mock anything.
    $container->set('git.log', $this->getMockBuilder(StdClass::class));
    $container->set('analyser', $this->getMockBuilder(StdClass::class));
    $container->set('waypoint_manager.branches', $this->getMockBuilder(StdClass::class));
    $container->set('waypoint_manager.patches', $this->getMockBuilder(StdClass::class));
    $container->set('drupal_org', $this->getMockBuilder(StdClass::class));
    $container->set('git.executor', $this->getMockBuilder(StdClass::class));

    // Add real versions of any remaining services not yet registered.
    $this->completeServiceContainer($container);

    $command_tester = $this->setUpCommandTester($container, 'patch', \Dorgflow\Command\CreatePatch::class);

    $this->expectException(\Exception::class);

    $command_tester->execute([
      'command'  => 'patch',
    ]);
  }

  /**
   * Tests the case where the feature branch can't be found.
   */
  public function testNoFeatureBranch() {
    $container = new \Symfony\Component\DependencyInjection\ContainerBuilder();

    $git_info = $this->createMock(\Dorgflow\Service\GitInfo::class);
    // Git is clean so the command proceeds.
    $git_info->method('gitIsClean')
      ->willReturn(TRUE);
    $branch_list = [
      // There is no feature branch.
      '8.x-2.x' => 'sha',
      'some-branch-name' => 'sha',
      'something-else' => 'sha',
    ];
    $git_info->method('getBranchList')
      ->willReturn($branch_list);
    $git_info->method('getBranchListReachable')
      ->willReturn($branch_list);
    $container->set('git.info', $git_info);

    // The analyser returns an issue number.
    $analyser = $this->createMock(\Dorgflow\Service\Analyser::class);
    $analyser->method('deduceIssueNumber')
      ->willReturn(123456);
    $container->set('analyser', $analyser);

    // The git log should not be called at all.
    $git_log = $this->createMock(\Dorgflow\Service\GitLog::class);
    $git_log->expects($this->never())->method($this->anything());
    $container->set('git.log', $git_log);

    // The git executor should not be called at all.
    $git_executor = $this->createMock(\Dorgflow\Service\GitExecutor::class);
    $git_executor->expects($this->never())->method($this->anything());
    $container->set('git.executor', $git_executor);

    // Drupal.org API should not be called at all.
    $drupal_org = $this->createMock(\Dorgflow\Service\DrupalOrg::class);
    $drupal_org->expects($this->never())->method($this->anything());
    $container->set('drupal_org', $drupal_org);

    // Need the real service for this, as we want the command to get the branch
    // object from it, based on the mocked git.info service.
    $container
      ->register('waypoint_manager.branches', \Dorgflow\Service\WaypointManagerBranches::class)
      ->addArgument(new Reference('git.info'))
      ->addArgument(new Reference('drupal_org'))
      ->addArgument(new Reference('git.executor'))
      ->addArgument(new Reference('analyser'));

    $container->set('waypoint_manager.patches', $this->getMockBuilder(\Dorgflow\Service\WaypointManagerPatches::class));

    // Add real versions of any remaining services not yet registered.
    $this->completeServiceContainer($container);

    $command_tester = $this->setUpCommandTester($container, 'patch', \Dorgflow\Command\CreatePatch::class);

    $this->expectException(\Exception::class);

    $command_tester->execute([
      'command'  => 'patch',
    ]);
  }

  /**
   * Tests the case where the feature branch isn't current.
   */
  public function testNotOnFeatureBranch() {
    $container = new \Symfony\Component\DependencyInjection\ContainerBuilder();

    $git_info = $this->createMock(\Dorgflow\Service\GitInfo::class);
    // Git is clean so the command proceeds.
    $git_info->method('gitIsClean')
      ->willReturn(TRUE);
    $branch_list = [
      // There is a feature branch.
      '123456-terrible-bug' => 'sha-feature',
      '8.x-2.x' => 'sha',
      'some-branch-name' => 'sha',
      'something-else' => 'sha',
    ];
    $git_info->method('getBranchList')
      ->willReturn($branch_list);
    $git_info->method('getBranchListReachable')
      ->willReturn($branch_list);
    $git_info->method('getCurrentBranch')
      ->willReturn('8.x-2.x');
    $container->set('git.info', $git_info);

    // The analyser returns an issue number.
    $analyser = $this->createMock(\Dorgflow\Service\Analyser::class);
    $analyser->method('deduceIssueNumber')
      ->willReturn(123456);
    $container->set('analyser', $analyser);

    // The git log should not be called at all.
    $git_log = $this->createMock(\Dorgflow\Service\GitLog::class);
    $git_log->expects($this->never())->method($this->anything());
    $container->set('git.log', $git_log);

    // The git executor should not be called at all.
    $git_executor = $this->createMock(\Dorgflow\Service\GitExecutor::class);
    $git_executor->expects($this->never())->method($this->anything());
    $container->set('git.executor', $git_executor);

    // Drupal.org API should not be called at all.
    $drupal_org = $this->createMock(\Dorgflow\Service\DrupalOrg::class);
    $drupal_org->expects($this->never())->method($this->anything());
    $container->set('drupal_org', $drupal_org);

    // Need the real service for this, as we want the command to get the branch
    // object from it, based on the mocked git.info service.
    $container
      ->register('waypoint_manager.branches', \Dorgflow\Service\WaypointManagerBranches::class)
      ->addArgument(new Reference('git.info'))
      ->addArgument(new Reference('drupal_org'))
      ->addArgument(new Reference('git.executor'))
      ->addArgument(new Reference('analyser'));

    $container->set('waypoint_manager.patches', $this->getMockBuilder(\Dorgflow\Service\WaypointManagerPatches::class));

    // Add real versions of any remaining services not yet registered.
    $this->completeServiceContainer($container);

    $command_tester = $this->setUpCommandTester($container, 'patch', \Dorgflow\Command\CreatePatch::class);

    $this->expectException(\Exception::class);

    $command_tester->execute([
      'command'  => 'patch',
    ]);
  }

  /**
   * Tests creating a patch with no previous patches.
   */
  public function testCreatePatch() {
    $container = new \Symfony\Component\DependencyInjection\ContainerBuilder();

    $git_info = $this->createMock(\Dorgflow\Service\GitInfo::class);
    // Git is clean so the command proceeds.
    $git_info->method('gitIsClean')
      ->willReturn(TRUE);
    $branch_list = [
      // There is a feature branch.
      '123456-terrible-bug' => 'sha-feature',
      '8.x-2.x' => 'sha',
      'some-branch-name' => 'sha',
      'something-else' => 'sha',
    ];
    $git_info->method('getBranchList')
      ->willReturn($branch_list);
    $git_info->method('getBranchListReachable')
      ->willReturn($branch_list);
    // Feature branch is current.
    $git_info->method('getCurrentBranch')
      ->willReturn('123456-terrible-bug');
    $container->set('git.info', $git_info);

    // The analyser returns an issue number and project name.
    $analyser = $this->createMock(\Dorgflow\Service\Analyser::class);
    $analyser->method('deduceIssueNumber')
      ->willReturn(123456);
    $analyser->method('getCurrentProjectName')
      ->willReturn('my_project');
    $container->set('analyser', $analyser);

    $drupal_org = $this->createMock(\Dorgflow\Service\DrupalOrg::class);
    $drupal_org->method('getNextCommentIndex')
      ->willReturn(16);
    $container->set('drupal_org', $drupal_org);

    $git_log = $this->createMock(\Dorgflow\Service\GitLog::class);
    $git_log->method('getFeatureBranchLog')
      ->willReturn([
        'sha-feature' => [
          'sha' => 'sha-feature',
          'message' => 'commit message',
        ],
      ]);
    // Not testing this output, so just return an empty array.
    $git_log->method('getPartialFeatureBranchLog')
      ->willReturn([]);
    $container->set('git.log', $git_log);

    $git_executor = $this->createMock(\Dorgflow\Service\GitExecutor::class);
    // A patch will be created.
    $git_executor->expects($this->once())
      ->method('createPatch')
      ->with(
        // Diff against the master branch.
        $this->equalTo('8.x-2.x'),
        // Patch name contains:
        //  - issue number
        //  - next comment number
        //  - project name
        //  - feature branch description
        $this->equalTo('123456-16.my_project.terrible-bug.patch')
      );
    $git_executor->expects($this->never())->method('checkOutFiles');
    $git_executor->expects($this->never())->method('applyPatch');
    // An empty commit will be made to the feature branch to show the new patch.
    $git_executor->expects($this->once())
      ->method('commit')
      ->with(
        $this->equalTo("Patch for Drupal.org. Comment (expected): 16; file: 123456-16.my_project.terrible-bug.patch. Automatic commit by dorgflow.")
      );
    $container->set('git.executor', $git_executor);

    // Add real versions of any remaining services not yet registered.
    $this->completeServiceContainer($container);

    $command_tester = $this->setUpCommandTester($container, 'patch', \Dorgflow\Command\CreatePatch::class);

    $command_tester->execute([
      'command'  => 'patch',
    ]);
  }

  /**
   * Tests creating a patch with a previous patch.
   */
  public function testCreatePatchWithInterdiff() {
    $container = new \Symfony\Component\DependencyInjection\ContainerBuilder();

    $git_info = $this->createMock(\Dorgflow\Service\GitInfo::class);
    // Git is clean so the command proceeds.
    $git_info->method('gitIsClean')
      ->willReturn(TRUE);
    $branch_list = [
      // There is a feature branch.
      '123456-terrible-bug' => 'sha-feature',
      '8.x-2.x' => 'sha',
      'some-branch-name' => 'sha',
      'something-else' => 'sha',
    ];
    $git_info->method('getBranchList')
      ->willReturn($branch_list);
    $git_info->method('getBranchListReachable')
      ->willReturn($branch_list);
    // Feature branch is current.
    $git_info->method('getCurrentBranch')
      ->willReturn('123456-terrible-bug');
    $container->set('git.info', $git_info);

    // The analyser returns an issue number and project name.
    $analyser = $this->createMock(\Dorgflow\Service\Analyser::class);
    $analyser->method('deduceIssueNumber')
      ->willReturn(123456);
    $analyser->method('getCurrentProjectName')
      ->willReturn('my_project');
    $container->set('analyser', $analyser);

    $drupal_org = $this->createMock(\Dorgflow\Service\DrupalOrg::class);
    $drupal_org->method('getNextCommentIndex')
      ->willReturn(16);
    $container->set('drupal_org', $drupal_org);

    $git_log = $this->createMock(\Dorgflow\Service\GitLog::class);
    $git_log->method('getFeatureBranchLog')
      ->willReturn([
        // Previous patch from Drupal.org which has been applied.
        'sha-patch-1' => [
          'sha' => 'sha-patch-1',
          'message' => 'Patch from Drupal.org. Comment: 1; URL: https://www.drupal.org/node/123456#comment-111; file: patch-1.patch; fid: 11. Automatic commit by dorgflow.',
        ],
        'sha-feature' => [
          'sha' => 'sha-feature',
          'message' => 'commit message',
        ],
      ]);
    // Not testing this output, so just return an empty array.
    $git_log->method('getPartialFeatureBranchLog')
      ->willReturn([]);
    $container->set('git.log', $git_log);

    $git_executor = $this->createMock(\Dorgflow\Service\GitExecutor::class);
    // A patch will be created.
    $git_executor->expects($this->exactly(2))
      ->method('createPatch')
      ->withConsecutive(
        [
          // Diff against the master branch.
          $this->equalTo('8.x-2.x'),
          // Patch name contains:
          //  - issue number
          //  - next comment number
          //  - project name
          //  - feature branch description
          $this->equalTo('123456-16.my_project.terrible-bug.patch'),
        ],
        [
          // Diff against the most recent patch.
         'sha-patch-1',
         // Interdiff file name
         'interdiff.123456.1-16.txt',
        ]
      );
    $git_executor->expects($this->never())->method('checkOutFiles');
    $git_executor->expects($this->never())->method('applyPatch');
    // An empty commit will be made to the feature branch to show the new patch.
    $git_executor->expects($this->once())
      ->method('commit')
      ->with(
        $this->equalTo("Patch for Drupal.org. Comment (expected): 16; file: 123456-16.my_project.terrible-bug.patch. Automatic commit by dorgflow.")
      );
    $container->set('git.executor', $git_executor);

    // Add real versions of any remaining services not yet registered.
    $this->completeServiceContainer($container);

    $command_tester = $this->setUpCommandTester($container, 'patch', \Dorgflow\Command\CreatePatch::class);

    $command_tester->execute([
      'command'  => 'patch',
    ]);
  }

  /**
   * Tests creating a patch with a previous patch after an earlier attempt.
   *
   * This covers the case when the user has done this:
   *  - $ dorgflow [creates a patch]
   *  - spots something they missed, doesn't upload the patch.
   *  - $ git commit ... [makes more commits]
   * The user is now about to make the patch again. The interdiff should ignore
   * the commit that the previous CreatePatch command made.
   */
  public function testCreatePatchWithInterdiffAndPriorAttempt() {
    $container = new \Symfony\Component\DependencyInjection\ContainerBuilder();

    $git_info = $this->createMock(\Dorgflow\Service\GitInfo::class);
    // Git is clean so the command proceeds.
    $git_info->method('gitIsClean')
      ->willReturn(TRUE);
    $branch_list = [
      // There is a feature branch.
      '123456-terrible-bug' => 'sha-feature',
      '8.x-2.x' => 'sha',
      'some-branch-name' => 'sha',
      'something-else' => 'sha',
    ];
    $git_info->method('getBranchList')
      ->willReturn($branch_list);
    $git_info->method('getBranchListReachable')
      ->willReturn($branch_list);
    // Feature branch is current.
    $git_info->method('getCurrentBranch')
      ->willReturn('123456-terrible-bug');
    $container->set('git.info', $git_info);

    // The analyser returns an issue number and project name.
    $analyser = $this->createMock(\Dorgflow\Service\Analyser::class);
    $analyser->method('deduceIssueNumber')
      ->willReturn(123456);
    $analyser->method('getCurrentProjectName')
      ->willReturn('my_project');
    $container->set('analyser', $analyser);

    $drupal_org = $this->createMock(\Dorgflow\Service\DrupalOrg::class);
    $drupal_org->method('getNextCommentIndex')
      ->willReturn(16);
    $container->set('drupal_org', $drupal_org);

    $git_log = $this->createMock(\Dorgflow\Service\GitLog::class);
    $git_log->method('getFeatureBranchLog')
      ->willReturn([
        // Previous patch from Drupal.org which has been applied.
        'sha-patch-1' => [
          'sha' => 'sha-patch-1',
          'message' => 'Patch from Drupal.org. Comment: 1; URL: https://www.drupal.org/node/123456#comment-111; file: patch-1.patch; fid: 11. Automatic commit by dorgflow.',
        ],
        'sha-1' => [
          'sha' => 'sha-1',
          'message' => 'my own commit',
        ],
        'sha-patch-local' => [
          'sha' => 'sha-patch-local',
          'message' => "Patch for Drupal.org. Comment (expected): 16; file: 123456-16.my_project.terrible-bug.patch. Automatic commit by dorgflow.",
        ],
        'sha-2' => [
          'sha' => 'sha-2',
          'message' => 'another commit',
        ],
        'sha-feature' => [
          'sha' => 'sha-feature',
          'message' => 'commit message',
        ],
      ]);
    // Not testing this output, so just return an empty array.
    $git_log->method('getPartialFeatureBranchLog')
      ->willReturn([]);
    $container->set('git.log', $git_log);

    $git_executor = $this->createMock(\Dorgflow\Service\GitExecutor::class);
    // A patch will be created.
    $git_executor->expects($this->exactly(2))
      ->method('createPatch')
      ->withConsecutive(
        [
          // Diff against the master branch.
          $this->equalTo('8.x-2.x'),
          // Patch name contains:
          //  - issue number
          //  - next comment number
          //  - project name
          //  - feature branch description
          $this->equalTo('123456-16.my_project.terrible-bug.patch'),
        ],
        [
          // Diff against the most recent patch.
         'sha-patch-1',
         // Interdiff file name
         'interdiff.123456.1-16.txt',
        ]
      );
    $git_executor->expects($this->never())->method('checkOutFiles');
    $git_executor->expects($this->never())->method('applyPatch');
    // An empty commit will be made to the feature branch to show the new patch.
    $git_executor->expects($this->once())
      ->method('commit')
      ->with(
        $this->equalTo("Patch for Drupal.org. Comment (expected): 16; file: 123456-16.my_project.terrible-bug.patch. Automatic commit by dorgflow.")
      );
    $container->set('git.executor', $git_executor);

    // Add real versions of any remaining services not yet registered.
    $this->completeServiceContainer($container);

    $command_tester = $this->setUpCommandTester($container, 'patch', \Dorgflow\Command\CreatePatch::class);

    $command_tester->execute([
      'command'  => 'patch',
    ]);
  }

}
