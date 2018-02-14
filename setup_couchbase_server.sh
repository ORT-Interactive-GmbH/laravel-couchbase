#!/usr/bin/env bash

set -e
echo "Install Couchbase $CB_VERSION"

if [[ $CB_VERSION == 5* ]]; then

    apt-get install python-httplib2 -yqq

    # Couchbase Server
    wget https://packages.couchbase.com/releases/5.0.1/couchbase-server-enterprise_5.0.1-debian8_amd64.deb
    dpkg -i couchbase-server-enterprise_5.0.1-debian8_amd64.deb

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
    # Couchbase Server
    wget https://packages.couchbase.com/releases/4.6.0-DP/couchbase-server-enterprise_4.6.0-DP-ubuntu12.04_amd64.deb
    dpkg -i couchbase-server-enterprise_4.6.0-DP-ubuntu12.04_amd64.deb

    # Bucket init
    /opt/couchbase/bin/couchbase-server -- -noinput -detached
    sleep 20

    /opt/couchbase/bin/couchbase-cli cluster-init -c 127.0.0.1:8091  --cluster-username=Administrator --cluster-password=password --cluster-port=8091 --cluster-index-ramsize=512 --cluster-ramsize=512 --services=data,query,index
    /opt/couchbase/bin/couchbase-cli bucket-create -c 127.0.0.1:8091 --bucket=$CB_DATABASE --bucket-type=couchbase --bucket-port=11211 --bucket-ramsize=512  --bucket-replica=1 -u Administrator -p password
    sleep 10

    /opt/couchbase/bin/cbq -e http://127.0.0.1:8091 --script "CREATE PRIMARY INDEX ON \`$CB_DATABASE\` USING GSI;"
fi