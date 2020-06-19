#!/usr/bin/env bash

set -e
echo "Install Couchbase $CB_VERSION"

if [[ $CB_VERSION == 6* ]]; then

    # Couchbase PHP SDK
    apt-get install -yqq $(apt-cache search libcouchbase | cut -d ' ' -f 1)
    test -f downloads/couchbase-server-enterprise_6.5.1-debian9_amd64.deb || curl --output downloads/couchbase-server-enterprise_6.5.1-debian9_amd64.deb https://packages.couchbase.com/releases/6.5.1/couchbase-server-enterprise_6.5.1-debian9_amd64.deb
    dpkg -i downloads/couchbase-server-enterprise_6.5.1-debian9_amd64.deb

    sleep 30
    netstat -tulpen

    # Install couchbase cluster
    /opt/couchbase/bin/couchbase-cli cluster-init -c 127.0.0.1:8091 --cluster-username=Administrator --cluster-password=password --cluster-port=8091 --cluster-index-ramsize=256 --cluster-fts-ramsize=256 --cluster-ramsize=2048 --services=data,index,query,fts
    sleep 5

    # Install couchbase bucket $CB_DATABASE
    /opt/couchbase/bin/couchbase-cli bucket-create -c 127.0.0.1:8091 -u Administrator -p password --wait --bucket=$CB_DATABASE --bucket-type=couchbase --bucket-ramsize=256  --bucket-replica=1
    sleep 10

    /opt/couchbase/bin/couchbase-cli user-manage -c 127.0.0.1:8091 -u Administrator -p password --set --rbac-username dbuser_backend --rbac-password password_backend --roles bucket_full_access[$CB_DATABASE] --auth-domain local
    /opt/couchbase/bin/couchbase-cli user-manage -c 127.0.0.1:8091 -u Administrator -p password --set --rbac-username dbuser_backend --rbac-password password_backend --roles bucket_full_access[$CB_DATABASE] --auth-domain external

    /opt/couchbase/bin/cbq -e 127.0.0.1:8093 -u Administrator -p password --script "CREATE PRIMARY INDEX ON \`$CB_DATABASE\` USING GSI;"

elif [[ $CB_VERSION == 5* ]]; then

    apt-get install libcouchbase-dev python-httplib2 -yqq

    # Couchbase Server
    test -f downloads/couchbase-server-enterprise_5.5.0-debian9_amd64.deb || curl --output downloads/couchbase-server-enterprise_5.0.1-debian9_amd64.deb https://packages.couchbase.com/releases/5.5.0/couchbase-server-enterprise_5.5.0-debian9_amd64.deb
    dpkg -i downloads/couchbase-server-enterprise_5.5.0-debian9_amd64.deb

    # Bucket init
    /opt/couchbase/bin/couchbase-server -- -noinput -detached
    sleep 20

    # Install couchbase cluster + bucket
    /opt/couchbase/bin/couchbase-cli cluster-init -c 127.0.0.1:8091 --cluster-username=Administrator --cluster-password=password --cluster-port=8091 --cluster-index-ramsize=256 --cluster-fts-ramsize=256 --cluster-ramsize=2048 --services=data,index,query,fts
    sleep 5

    /opt/couchbase/bin/couchbase-cli bucket-create -c 127.0.0.1:8091 -u Administrator -p password --wait --bucket=$CB_DATABASE --bucket-type=couchbase --bucket-port=11212 --bucket-ramsize=256  --bucket-replica=1
    sleep 10

    /opt/couchbase/bin/couchbase-cli user-manage -c 127.0.0.1:8091 -u Administrator -p password --set --rbac-username dbuser_backend --rbac-password password_backend --roles bucket_full_access[$CB_DATABASE] --auth-domain local
    /opt/couchbase/bin/couchbase-cli user-manage -c 127.0.0.1:8091 -u Administrator -p password --set --rbac-username dbuser_backend --rbac-password password_backend --roles bucket_full_access[$CB_DATABASE] --auth-domain external

    /opt/couchbase/bin/cbq -e 127.0.0.1:8093 -u Administrator -p password --script "CREATE PRIMARY INDEX ON \`$CB_DATABASE\` USING GSI;"
else
    # Couchbase PHP SDK
    apt-get install libcouchbase-dev -yqq

    # Couchbase Server
    test -f downloads/couchbase-server-enterprise_4.6.0-DP-ubuntu12.04_amd64.deb || curl --output downloads/couchbase-server-enterprise_4.6.0-DP-ubuntu12.04_amd64.deb https://packages.couchbase.com/releases/4.6.0-DP/couchbase-server-enterprise_4.6.0-DP-ubuntu12.04_amd64.deb
    dpkg -i downloads/couchbase-server-enterprise_4.6.0-DP-ubuntu12.04_amd64.deb

    # Bucket init
    /opt/couchbase/bin/couchbase-server -- -noinput -detached
    sleep 20

    /opt/couchbase/bin/couchbase-cli cluster-init -c 127.0.0.1:8091  --cluster-username=Administrator --cluster-password=password --cluster-port=8091 --cluster-index-ramsize=512 --cluster-ramsize=512 --services=data,query,index
    /opt/couchbase/bin/couchbase-cli bucket-create -c 127.0.0.1:8091 --bucket=$CB_DATABASE --bucket-type=couchbase --bucket-port=11211 --bucket-ramsize=512  --bucket-replica=1 -u Administrator -p password
    sleep 10

    /opt/couchbase/bin/cbq -e http://127.0.0.1:8091 --script "CREATE PRIMARY INDEX ON \`$CB_DATABASE\` USING GSI;"
fi
