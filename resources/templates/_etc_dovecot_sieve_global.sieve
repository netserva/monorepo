require ["vnd.dovecot.execute", "fileinto", "envelope", "variables", "editheader"];

# Skip processing for system messages
if not exists ["Subject"] { fileinto "Trash"; stop; }
if header :contains "from" ["root@", "daemon@", "postmaster@"] {
  fileinto "Trash"; stop;
} elsif header :contains "to" ["root@", "daemon@", "postmaster@"] {
  fileinto "Trash"; stop;
}

# Extract localpart and domain for bogofilter database path
if envelope :localpart :matches "to" "*" { set "lhs" "${1}"; }
if envelope :domain :matches "to" "*" { set "rhs" "${1}"; }

# Run bogofilter and capture result (Ham, Spam, or Unsure)
execute :pipe :output "SCORE" "bogofilter" ["-d", "/srv/${rhs}/msg/${lhs}/.bogofilter"];

# Add X-Bogosity header with classification
if string :matches "${SCORE}" "*" {
  addheader :last "X-Bogosity" "${SCORE}";
}

# File spam messages to Junk folder
if string :matches "${SCORE}" "Spam*" {
  fileinto "Junk";
  stop;
}

# File unsure messages to Unsure folder for manual review/training
if string :matches "${SCORE}" "Unsure*" {
  fileinto "Unsure";
  stop;
}

# Ham messages go to INBOX (implicit keep)
