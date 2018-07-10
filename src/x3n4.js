function x3n4 (opt) {
  'use strict';

  /* Options */
  var defaults = {
    version: 'v1.0.0',
    endpoint: 'x3n4.php',
    encryption: 'rb64',
  };
  var options = $.extend(true, defaults, opt);

  /* Encryption / Decryption */
  var encrypt = function(input) {
    switch (options.encryption) {
      case 'b64':
      return window.btoa(input);
      case 'rb64':
      return window.btoa(input).split('').reverse().join('');
    }
    return input;
  };
  var decrypt = function(input) {
    switch (options.encryption) {
      case 'b64':
      return window.atob(input);
      case 'rb64':
      return window.atob(input.split('').reverse().join(''));
    }
    return input;
  };

  /* Command-line interface */
  var CLI = function (a, b, c) {
    /* UI Containers */
    var stdout = a;
    var stdin = b;
    var button = c;
    /* Commands history */
    var history = {
      list: JSON.parse(window.localStorage.getItem('history') || '[]'),
      position: -1,
      up: function() {
        if (this.list.length === 0) return;
        if (this.position < (this.list.length - 1)) {
          this.position++;
        }
        this.render();
      },
      down: function () {
        if (this.list.length === 0) return;
        if (this.position >= 0) {
          this.position--;
        }
        this.render();
      },
      render: function () {
        stdin.val(this.list[(this.list.length - 1) - this.position]);
      },
      add: function (value) {
        this.list.push(value);
        this.position = -1;
        this.save();
      },
      clean: function () {
        this.list = [];
        window.localStorage.removeItem('history');
      },
      save: function () {
        window.localStorage.setItem('history', JSON.stringify(this.list));
      }
    };
    /* Console object */
    var self = {
      out: {
        clean: function() {
          stdout.html('');
        },
        write: function(html) {
          stdout.append(html);
          stdout.scrollTop(stdout[0].scrollHeight);
        }
      },
      in: {
        disable: function() {
          stdin.prop('disabled', true);
        },
        enable: function() {
          stdin.prop('disabled', false);
        },
        reset: function() {
          this.enable();
          stdin.val('').focus();
        },
        read: function() {
          return stdin.val();
        }
      },
      history: history,
      getBanner: function() {
        return $('#pwd').html();
      },
      setBanner: function(banner) {
        $('#pwd').html(banner);
      },
      sendCommand: function(command) {
        this.in.disable();
        this.history.add(command);
        /* Internal command handler */
        switch (command.trim()) {
          case 'clear':
            this.out.clean();
            this.in.reset();
            return;
          case 'exit':
            this.history.clean();
            break;
        }
        this.out.write(this.getBanner() + ' ' + command + '\n');
        /* Server-side command handler */
        var that = this;
        $.post(options.endpoint, {cmd: encrypt(command)}, function(data) {
          data = JSON.parse(decrypt(data));
          that.setBanner(data.banner);
          that.out.write(data.stdout);
          that.in.reset();
        });
      }
    };
    /* Console UI Callbacks */
    var onclick = function (ev) {
      self.sendCommand(self.in.read());
      self.in.reset();
    };
    var keypress = function (ev) {
      if ((ev.keyCode ? ev.keyCode : ev.which) == '13') {
        button.click();
      }
    };
    var keydown = function (ev) {
      var code = ev.keyCode ? ev.keyCode : ev.which;
      switch (code) {
        case 38:
        history.up();
        break;
        case 40:
        history.down();
        break;
      }
    };
    button.on('click', onclick);
    stdin.on('keypress', keypress);
    stdin.on('keydown', keydown);
    /* return instance */
    return self;
  };

  var checkUpdate = function() {
    var that = this;
    $.get('https://api.github.com/repos/jorge-matricali/x3n4/releases', function(data) {
      if (that.options.version !== data[0].tag_name) {
        that.console.out.write('/!\\ x3n4 ' + data[0].tag_name + " available. Type 'upgrade' to download the latest version automatically.\n");
      }
    });
  };

  return {
    options: options,
    console: new CLI($('#stdout'), $('#stdin'), $('#btnExecCommand')),
    checkUpdate: checkUpdate,
  };
}
