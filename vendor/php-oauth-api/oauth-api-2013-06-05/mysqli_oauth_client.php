<?php
/*
 * mysqli_oauth_client.php
 *
 * @(#) $Id: mysqli_oauth_client.php,v 1.3 2013/06/05 11:53:42 mlemos Exp $
 *
 */

class mysqli_oauth_client_class extends database_oauth_client_class
{
	var $db;
	var $database = array(
		'host'=>'',
		'user'=>'',
		'password'=>'',
		'name'=>'',
		'port'=>0,
		'socket'=>''
	);

	Function Initialize()
	{
		if(!parent::Initialize())
			return false;
		$this->db = new mysqli($this->database['host'], $this->database['user'], $this->database['password'], $this->database['name'], $this->database['port'], $this->database['socket']);
		if($this->db->connect_errno)
		{
			$this->SetError($this->db->connect_error);
			$this->db = null;
			return false;
		}
		return true;
	}

	Function Finalize($success)
	{
		if(IsSet($this->db))
		{
			$this->db->close();
			$this->db = null;
		}
		return parent::Finalize($success);
	}

	Function Query($sql, $parameters, &$results)
	{
		if($this->debug)
			$this->OutputDebug('Query: '.$sql);
		$results = array();
		$statement = $this->db->stmt_init();
		if(!$statement->prepare($sql))
			return $this->SetError($statement->error);
		$prepared = array();
		$types = '';
		$tp = count($parameters);
		$v = $parameters;
		for($p = 0; $p < $tp;)
		{
			switch($t = $v[$p++])
			{
				case 's':
				case 'i':
				case 'd':
					break;
				case 'b':
					$v[$p] = (IsSet($v[$p]) ? ($v[$p] ? 'Y' : 'N') : null);
				case 't':
				case 'dt':
				case 'ts':
					$t = 's';
					break;
			}
			$types .= $t;
			if($this->debug)
				$this->OutputDebug('Query parameter type: '.$t.' value: '.$v[$p]);
			$prepared[] = &$v[$p++];
		}
		array_unshift($prepared, $types);
		if(!call_user_func_array(array($statement, 'bind_param'), $prepared)
		|| !$statement->execute())
		{
			$statement->close();
			return $this->SetError($statement->error);
		}
		$fields = $statement->field_count;
		if($fields)
		{
			$row = $bind = array();
			for($f = 0; $f < $fields; ++$f)
			{
				$row[$f] = null;
				$bind[$f] = &$row[$f];
			}
			if(!call_user_func_array(array($statement, 'bind_result'), $bind))
			{
				$statement->close();
				return $this->SetError($statement->error);
			}
			$rows = array();
			while(($success = $statement->fetch()) !== null)
			{
				if(!$success)
				{
					$statement->close();
					return $this->SetError($statement->error);
				}
				$rows[] = $row;
			}
			$results['rows'] = $rows;
		}
		elseif(strlen($error = $statement->error))
		{
			$statement->close();
			return $this->SetError($error);
		}
		else
		{
			$results['insert_id'] = $statement->insert_id;
			$results['affected_rows'] = $statement->affected_rows;
		}
		$statement->close();
		return true;
	}
};

?>