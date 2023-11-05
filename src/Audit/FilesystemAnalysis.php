<?php

namespace Drutiny\Acquia\Audit;

use Drutiny\Attribute\DataProvider;
use Drutiny\Attribute\Parameter;
use Drutiny\Attribute\Type;

/**
 * Audit the usage of the filesystem.
 */
#[Parameter(
  name: 'unit', 
  description: 'the unit of measurement to describe the volume usage in. E.g. B,M,G,T.', 
  mode: Parameter::OPTIONAL,
  default: 'G',
  type: Type::STRING,
  enums: ['B', 'M', 'G', 'T'],
)]
class FilesystemAnalysis extends EnvironmentAnalysis
{

    #[DataProvider(1)]
    public function gatherFileSystemData()
    {
        $unit = $this->getParameter('unit', "G");

        $app = $this->get('app');

        // Report file system disk space and inode usage.
        if ($app['hosting']['type'] != 'acp') {
          $output = $this->target->run("df -B$unit | grep gfs && df --inodes | grep gfs");
        } else {
          $output = $this->target->run("df -B$unit | grep ebs1 && df --inodes | grep ebs1");
        }
        list($disk, $inode) = array_values(explode(PHP_EOL, $output));

        // Remove all occurrences the storage unit and % from the output.
        // This will allow the values to be used in conditional expressions.
        $disk = str_replace([$unit,'%'], '', $disk);

        // Parse the usage data into variables.
        list($disk_volume, $disk_capacity, $disk_used, $disk_free, $disk_usage, $disk_mountpoint) = array_values(array_filter(preg_split("/\t|\s/", $disk)));
        list($inode_volume, $inode_capacity, $inode_used, $inode_free, $inode_usage, $inode_mountpoint) = array_values(array_filter(preg_split("/\t|\s/", $inode)));

        $this->set(
          'filesystem', [
            'disk' => [
              'volume' => $disk_volume,
              'capacity' => (int)$disk_capacity,
              'used' => (int)$disk_used,
              'free' => (int)$disk_free,
              'percent_used' => (int)$disk_usage,
              'mountpoint' => $disk_mountpoint,
              'unit' => $unit,
             ],
            'inode' => [
              'volume' => $inode_volume,
              'capacity' => (int)$inode_capacity,
              'used' => (int)$inode_used,
              'free' => (int)$inode_free,
              'percent_used' => (int)$inode_usage,
              'mountpoint' => $inode_mountpoint,
            ]
          ]
        );
    }
}
