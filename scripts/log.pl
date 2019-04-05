use strict;
use vars qw($NAME $VERSION %IRSSI);
use Irssi qw(command_bind signal_add_first settings_get_str settings_add_str get_irssi_dir settings_set_str);

$VERSION = "1.00";
%IRSSI = (
    authors     => "Paul Staroch",
    contact     => "paulchen\@rueckgr.at",
    name        => "log",
    description => "calls ircdb.py",
    license     => "GPL"
);

# === EDIT THE FOLLOWING LINES TO YOUR NEEDS ===

# === DO NOT EDIT ANYTHING BELOW THIS LINE ===

sub fut {
	my ($server, $msg, $nick_asks, $address, $channel) = @_;

	if($channel ne '#chatbox') {
		return;
	}

	system('/home/ircbot/ircdb/ircdb.py &');
}

sub fut_own {
	my ($server, $msg, $channel) = @_;
	fut($server, $msg, $server->{nick}, "", $channel);
}

signal_add_first 'message public' => 'fut';
signal_add_first 'message irc action' => 'fut';
signal_add_first 'message own_public' => 'fut_own';
signal_add_first 'message irc own_action' => 'fut_own';


