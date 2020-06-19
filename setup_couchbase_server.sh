#!/usr/bin/env bash

set -e

if [[ $CB_VERSION == 6* ]]; then


    # Couchbase PHP SDK
    apt-get install -yqq $(apt-cache search libcouchbase | cut -d ' ' -f 1)
    test -f downloads/couchbase-server-enterprise_6.5.1-debian${DEBIAN_VERSION}_amd64.deb || curl --output downloads/couchbase-server-enterprise_6.5.1-debian${DEBIAN_VERSION}_amd64.deb https://packages.couchbase.com/releases/6.5.1/couchbase-server-enterprise_6.5.1-debian${DEBIAN_VERSION}_amd64.deb
    dpkg -i downloads/couchbase-server-enterprise_6.5.1-debian${DEBIAN_VERSION}_amd64.deb

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
    exit 0
fi

echo "INVALID COUCHBASE VERSION"
exit 1
