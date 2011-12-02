#!/bin/bash


#script_dirname=`dirname $0`;
# get where the core directory resides
core_path="$( readlink -f "$( dirname "$0" )/" )";
core_parent_path="$( readlink -f "$( dirname "$0" )/../" )";

module='';
model='';
path='app';
help=0;
for p in $*;
    do
      [[ $p =~ ^--path=(.*) ]] && path="${BASH_REMATCH[1]}";
      [[ $p =~ ^([A-Z][a-z0-9].*)\.([A-Z][a-z0-9].*) ]] && module="${BASH_REMATCH[1]}" && model="${BASH_REMATCH[2]}";
      [[ $p =~ ^--help ]] && help=1;
    done;

if [ $help -eq 1 ]
then
  cat <<EOF
Generate Model:
  core/model.sh [--path=<application path>] <module>.<model>

Example
  core/model.sh Blog.Post
  core/model.sh --path='/var/sites/app' Blog.Post

EOF
  exit;
fi

if [ -z $module ]
then
  echo "Model didn't pass the pattern [A-Z][a-z0-9A-Z].*).([A-Z][a-z].*)"
  echo "Example: Blog.Post"
  exit
fi

if [ -z $model ]
then
  echo "Model didn't pass the pattern [A-Z][a-z].*).([A-Z][a-z].*)";
  echo "Example: Blog.Post"
  exit
fi

path="$( readlink -f "$( basename "$path" )" )";
pathname=`basename $path`;
filenamepath="$path/modules/`echo $module | tr '[A-Z]' '[a-z]'`/`echo $model | tr '[A-Z]' '[a-z]'`.php";

cat > $filenamepath <<EOF
<?php
namespace Core\App\Modules\\$module;

use \Core\Exception;
use \Core\Model;

class $model extends Model
{
    public static \$Name            = '$model';
    public static \$Table_Name      = '';
    public static \$Find_Options    = array();

    protected function initialize()
    {
        \$self = \$this;

        /**
         * HasMany Associations
         *
         * \$this->(name) = Model::HasMany( '<Module>.<Class>', '[<foreign table>.]<foreign key>', find_options_array, initialize_function );
         */

        /**
         * BelongsTo Associations
         *
         * \$this->(name) = Model::BelongsTo( '<Module>.<Class>', '[<foreign table>.]<foreign key>', find_options_array, initialize_function );
         */

    }

    protected function sanitize($sanitize)
    {
        // $sanitize["<field name>"]->{rule} = {default value};
    }

    protected function validate($validate)
    {
      // $validate["<field name>"]->{rule} = {error message};
    }

}
EOF

cat <<EOF
Model Class $model Created
In $filenamepath
EOF
