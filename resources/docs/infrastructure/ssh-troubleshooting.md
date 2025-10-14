# SSH Troubleshooting Guide

## SSH Multiplexing (ControlMaster) Issues

### Problem: SSH/SCP/SFTP behaves strangely after remote config changes

**Symptoms:**
- `scp: Connection closed` errors
- `subsystem request for sftp failed` in remote logs
- Commands work after reconnecting but fail initially
- Config changes on remote server don't take effect for existing connections

**Root Cause:**

SSH ControlMaster (multiplexing) keeps persistent connections in socket files at `~/.ssh/mux/`.
When the remote SSH daemon config changes (e.g., SFTP subsystem updates, chroot changes),
existing multiplexed connections continue using the **old configuration** from when they
were established.

**Solution:**

Remove the stale multiplexer socket:

```bash
# List active mux sockets
ls -la ~/.ssh/mux/

# Remove specific host socket
rm ~/.ssh/mux/192.168.1.227_22_root=

# Or remove all mux sockets (forces fresh connections)
rm ~/.ssh/mux/*
```

After removing the socket, the next SSH/SCP command will establish a fresh connection
with the current server configuration.

### SSH Multiplexing Configuration

NetServa uses SSH ControlMaster for performance. Check your `~/.ssh/config`:

```ssh-config
Host *
    ControlMaster auto
    ControlPath ~/.ssh/mux/%C
    ControlPersist 600
```

**Benefits:**
- Faster connections (reuses existing TCP connections)
- Reduced authentication overhead
- Lower latency for repeated commands

**Drawbacks:**
- Can cache stale connection state
- May mask remote configuration changes
- Requires manual cleanup when troubleshooting

### When to Clear Mux Sockets

Clear multiplexer sockets after:

1. **Remote SSHD config changes:**
   - SFTP subsystem changes (`internal-sftp` ↔ `/usr/lib/openssh/sftp-server`)
   - Port forwarding settings
   - Chroot configuration changes
   - Authentication method changes

2. **Remote SSHD restart/reload:**
   - Config might have changed
   - Server process replaced

3. **Strange SSH behavior:**
   - Unexpected authentication failures
   - SFTP/SCP failures with unclear errors
   - Commands work interactively but fail in scripts
   - Different behavior between manual and automated connections

4. **After system updates:**
   - OpenSSH package upgrades on remote server
   - SSH library updates

### Debugging SSH Issues

**Enable verbose output:**

```bash
ssh -vvv markc "command"          # Very verbose
scp -vvv file.txt markc:/path/    # Debug SCP
sftp -vvv markc                   # Debug SFTP
```

**Check remote logs:**

```bash
ssh markc "sudo journalctl -u ssh -n 50 --no-pager"
ssh markc "sudo tail -f /var/log/auth.log"
```

**Test SSHD config:**

```bash
ssh markc "sudo sshd -t"          # Test config syntax
ssh markc "sudo sshd -T"          # Dump effective config
```

**Verify SFTP subsystem:**

```bash
ssh markc "grep 'Subsystem sftp' /etc/ssh/sshd_config"
ssh markc "dpkg -L openssh-sftp-server | grep sftp-server"
```

### Common SSH/SFTP Issues

#### Issue: "subsystem request for sftp failed, subsystem not found"

**Causes:**
1. `internal-sftp` configured but not available (not compiled into sshd)
2. External `sftp-server` path incorrect
3. Chroot environment missing required binaries

**Fix:**
```bash
# Use external sftp-server instead
ssh markc "sudo sed -i 's|internal-sftp|/usr/lib/openssh/sftp-server|' /etc/ssh/sshd_config"
ssh markc "sudo systemctl restart ssh"

# Clear mux socket
rm ~/.ssh/mux/192.168.1.227_22_root=
```

#### Issue: "Bad configuration option" in sshd_config

**Cause:** Invalid syntax or accidental paste into config file

**Fix:**
```bash
# Test config
ssh markc "sudo sshd -t 2>&1"

# Common culprit: shell prompt accidentally pasted at top
ssh markc "sudo sed -i '1d' /etc/ssh/sshd_config"  # Remove first line
```

#### Issue: "Missing privilege separation directory: /run/sshd"

**Fix:**
```bash
ssh markc "sudo mkdir -p /run/sshd && sudo chmod 0755 /run/sshd"
ssh markc "sudo systemctl restart ssh"
```

### Best Practices

1. **Always clear mux sockets after remote SSH config changes**
2. **Use `-O exit` to explicitly close multiplexed connections:**
   ```bash
   ssh -O exit markc
   ```
3. **Test with fresh connections when troubleshooting:**
   ```bash
   ssh -o ControlPath=none markc "command"  # Bypass multiplexing
   ```
4. **Keep a backup of working sshd_config:**
   ```bash
   ssh markc "sudo cp /etc/ssh/sshd_config /etc/ssh/sshd_config.backup"
   ```
5. **Always test config before restarting:**
   ```bash
   ssh markc "sudo sshd -t && sudo systemctl restart ssh"
   ```

---

**Quick Reference:**

```bash
# Troubleshooting checklist
rm ~/.ssh/mux/*                                     # Clear stale connections
ssh -vvv markc "command"                            # Verbose debug
ssh markc "sudo sshd -t"                            # Test config
ssh markc "sudo journalctl -u ssh -n 20"            # Check logs
ssh markc "sudo systemctl restart ssh"              # Restart service
```
