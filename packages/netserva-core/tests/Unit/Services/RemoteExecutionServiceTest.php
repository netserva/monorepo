<?php

use NetServa\Core\Services\RemoteExecutionService;

beforeEach(function () {
    $this->service = $this->partialMock(RemoteExecutionService::class);
});

describe('OS Detection', function () {
    it('detects Debian OS from os-release', function () {
        $osReleaseContent = <<<'OSRELEASE'
PRETTY_NAME="Debian GNU/Linux trixie/sid"
NAME="Debian GNU/Linux"
ID=debian
VERSION_ID="13"
VERSION="13 (trixie)"
VERSION_CODENAME=trixie
HOME_URL="https://www.debian.org/"
SUPPORT_URL="https://www.debian.org/support"
BUG_REPORT_URL="https://bugs.debian.org/"
OSRELEASE;

        $this->service
            ->shouldReceive('executeAsRoot')
            ->with('testhost', 'cat /etc/os-release', false)
            ->andReturn([
                'success' => true,
                'output' => $osReleaseContent,
                'exit_code' => 0,
            ]);

        $this->service->shouldAllowMockingProtectedMethods();

        $result = $this->service->detectRemoteOs('testhost');

        expect($result)->toBeArray()
            ->and($result['ID'])->toBe('debian')
            ->and($result['VERSION_CODENAME'])->toBe('trixie')
            ->and($result['NAME'])->toBe('Debian GNU/Linux');
    });

    it('detects Ubuntu OS from os-release', function () {
        $osReleaseContent = <<<'OSRELEASE'
PRETTY_NAME="Ubuntu 24.04 LTS"
NAME="Ubuntu"
VERSION_ID="24.04"
VERSION="24.04 LTS (Noble Numbat)"
VERSION_CODENAME=noble
ID=ubuntu
ID_LIKE=debian
HOME_URL="https://www.ubuntu.com/"
SUPPORT_URL="https://help.ubuntu.com/"
BUG_REPORT_URL="https://bugs.launchpad.net/ubuntu/"
OSRELEASE;

        $this->service
            ->shouldReceive('executeAsRoot')
            ->with('testhost', 'cat /etc/os-release', false)
            ->andReturn([
                'success' => true,
                'output' => $osReleaseContent,
                'exit_code' => 0,
            ]);

        $this->service->shouldAllowMockingProtectedMethods();

        $result = $this->service->detectRemoteOs('testhost');

        expect($result)->toBeArray()
            ->and($result['ID'])->toBe('ubuntu')
            ->and($result['VERSION_CODENAME'])->toBe('noble')
            ->and($result['VERSION_ID'])->toBe('24.04');
    });

    it('detects Alpine Linux from os-release', function () {
        $osReleaseContent = <<<'OSRELEASE'
NAME="Alpine Linux"
ID=alpine
VERSION_ID=3.19.1
PRETTY_NAME="Alpine Linux v3.19"
HOME_URL="https://alpinelinux.org/"
BUG_REPORT_URL="https://gitlab.alpinelinux.org/alpine/aports/-/issues"
OSRELEASE;

        $this->service
            ->shouldReceive('executeAsRoot')
            ->with('testhost', 'cat /etc/os-release', false)
            ->andReturn([
                'success' => true,
                'output' => $osReleaseContent,
                'exit_code' => 0,
            ]);

        $this->service->shouldAllowMockingProtectedMethods();

        $result = $this->service->detectRemoteOs('testhost');

        expect($result)->toBeArray()
            ->and($result['ID'])->toBe('alpine')
            ->and($result['VERSION_ID'])->toBe('3.19.1')
            ->and($result['NAME'])->toBe('Alpine Linux');
    });

    it('returns null when os-release is not accessible', function () {
        $this->service
            ->shouldReceive('executeAsRoot')
            ->with('testhost', 'cat /etc/os-release', false)
            ->andReturn([
                'success' => false,
                'output' => '',
                'exit_code' => 1,
            ]);

        $this->service->shouldAllowMockingProtectedMethods();

        $result = $this->service->detectRemoteOs('testhost');

        expect($result)->toBeNull();
    });
});

describe('OS Variables Mapping', function () {
    it('maps Debian to correct OS variables', function () {
        $osReleaseContent = <<<'OSRELEASE'
ID=debian
VERSION_CODENAME=trixie
OSRELEASE;

        $this->service
            ->shouldReceive('executeAsRoot')
            ->with('testhost', 'cat /etc/os-release', false)
            ->andReturn([
                'success' => true,
                'output' => $osReleaseContent,
                'exit_code' => 0,
            ]);

        $this->service->shouldAllowMockingProtectedMethods();

        $result = $this->service->getOsVariables('testhost');

        expect($result)->toBeArray()
            ->and($result['OSTYP'])->toBe('debian')
            ->and($result['OSREL'])->toBe('trixie')
            ->and($result['OSMIR'])->toBe('deb.debian.org');
    });

    it('maps Ubuntu to correct OS variables', function () {
        $osReleaseContent = <<<'OSRELEASE'
ID=ubuntu
VERSION_CODENAME=noble
OSRELEASE;

        $this->service
            ->shouldReceive('executeAsRoot')
            ->with('testhost', 'cat /etc/os-release', false)
            ->andReturn([
                'success' => true,
                'output' => $osReleaseContent,
                'exit_code' => 0,
            ]);

        $this->service->shouldAllowMockingProtectedMethods();

        $result = $this->service->getOsVariables('testhost');

        expect($result)->toBeArray()
            ->and($result['OSTYP'])->toBe('ubuntu')
            ->and($result['OSREL'])->toBe('noble')
            ->and($result['OSMIR'])->toBe('archive.ubuntu.com');
    });

    it('maps Alpine to correct OS variables', function () {
        $osReleaseContent = <<<'OSRELEASE'
ID=alpine
VERSION_ID=3.19.1
OSRELEASE;

        $this->service
            ->shouldReceive('executeAsRoot')
            ->with('testhost', 'cat /etc/os-release', false)
            ->andReturn([
                'success' => true,
                'output' => $osReleaseContent,
                'exit_code' => 0,
            ]);

        $this->service->shouldAllowMockingProtectedMethods();

        $result = $this->service->getOsVariables('testhost');

        expect($result)->toBeArray()
            ->and($result['OSTYP'])->toBe('alpine')
            ->and($result['OSREL'])->toBe('3.19.1')
            ->and($result['OSMIR'])->toBe('dl-cdn.alpinelinux.org');
    });

    it('returns unknown values when OS detection fails', function () {
        $this->service
            ->shouldReceive('executeAsRoot')
            ->with('testhost', 'cat /etc/os-release', false)
            ->andReturn([
                'success' => false,
                'output' => '',
                'exit_code' => 1,
            ]);

        $this->service->shouldAllowMockingProtectedMethods();

        $result = $this->service->getOsVariables('testhost');

        expect($result)->toBeArray()
            ->and($result['OSTYP'])->toBe('unknown')
            ->and($result['OSREL'])->toBe('unknown')
            ->and($result['OSMIR'])->toBe('unknown');
    });
});
